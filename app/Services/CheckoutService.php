<?php

namespace App\Services;

use App\Jobs\IssueInvoiceJob;
use App\Jobs\PrepareOrderDownloads;
use App\Jobs\StornoInvoiceJob;
use App\Mail\OrderConfirmationMail;
use App\Mail\OrderRefundedMail;
use App\Models\Coupon;
use App\Models\Media;
use App\Models\Order;
use App\Services\Invoicing\InvoiceManager;
use App\Services\Payments\PaymentException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Rendeles-letrehozas (fizetes elott) + a fizetes visszaigazolasa utani lezaras
 * (download token kiadasa, kuponhasznalat szamlalasa, visszaigazolo e-mail).
 * A tenyleges szolgaltato-hivas kulon az App\Services\Payments\* osztalyokban
 * van, hogy ez a service fizetesi szolgaltato nelkul, egyszeruen tesztelheto maradjon.
 */
class CheckoutService
{
    public function __construct(private CouponService $coupons) {}

    /**
     * @param  int[]  $mediaIds
     * @param  array<string, mixed>  $billing  billing_name / billing_country / billing_zip / billing_city / billing_address / billing_tax_number
     *
     * @throws ValidationException ha nincs ervenyes (ready statuszu) media a listaban
     */
    public function createPendingOrder(array $mediaIds, string $email, ?string $couponCode, bool $plateConsent = false, array $billing = []): Order
    {
        $media = Media::query()->whereIn('id', $mediaIds)->where('status', Media::STATUS_READY)->get();

        if ($media->isEmpty()) {
            throw ValidationException::withMessages(['media_ids' => 'A kosárban lévő tartalom már nem elérhető.']);
        }

        $subtotal = (int) $media->sum('price_cents');
        $coupon = null;
        $discount = 0;

        if (filled($couponCode)) {
            $result = $this->coupons->apply($couponCode, $subtotal);
            $coupon = $result['coupon'];
            $discount = $result['discount_cents'];
        }

        $billingFields = collect(['billing_name', 'billing_country', 'billing_zip', 'billing_city', 'billing_address', 'billing_tax_number'])
            ->mapWithKeys(fn (string $k) => [$k => filled($billing[$k] ?? null) ? trim((string) $billing[$k]) : null])
            ->all();

        $termsAcceptedAt = ! empty($billing['terms_accepted']) ? now() : null;

        return DB::transaction(function () use ($media, $email, $coupon, $subtotal, $discount, $plateConsent, $billingFields, $termsAcceptedAt) {
            $order = Order::create([
                'buyer_email' => $email,
                'total_cents' => max(0, $subtotal - $discount),
                'payment_status' => Order::STATUS_PENDING,
                'coupon_id' => $coupon?->id,
                'discount_cents' => $discount,
                'plate_consent' => $plateConsent,
                'terms_accepted_at' => $termsAcceptedAt,
                ...$billingFields,
            ]);

            foreach ($media as $item) {
                $order->media()->attach($item->id, ['price_cents' => $item->price_cents]);
            }

            return $order;
        });
    }

    /**
     * A fizetési szolgáltató tranzakció-hivatkozását rogziti a rendelesen, hogy a
     * visszateres/IPN/webhook is meg tudja talalni (idempotens visszakereses).
     */
    public function attachPaymentReference(Order $order, string $provider, string $reference, ?string $providerOrderRef = null): void
    {
        $order->forceFill([
            'payment_provider' => $provider,
            'payment_provider_reference' => $reference,
            'payment_provider_order_ref' => $providerOrderRef,
        ])->save();
    }

    /**
     * Visszatérítés a szolgáltatónál + a rendelés lezárása. Teljes visszatérítéskor
     * a letöltési token is érvénytelenné válik és a kupon-használat visszaáll.
     *
     * @param  int|null  $amountCents  forint; null = a még vissza nem térített teljes összeg
     *
     * @throws PaymentException
     */
    public function refund(Order $order, ?int $amountCents = null, ?string $reason = null): void
    {
        $remaining = max(0, $order->total_cents - $order->refunded_cents);
        $amount = $amountCents === null ? $remaining : min($remaining, max(1, $amountCents));

        if (! $order->isRefundable() || $amount < 1) {
            throw ValidationException::withMessages(['order' => 'Ez a rendelés nem téríthető vissza.']);
        }

        $result = app(PaymentGatewayManager::class)->for($order->payment_provider)->refund($order, $amount);

        DB::transaction(function () use ($order, $amount, $reason, $result) {
            $order->refunded_cents += $amount;
            $order->refund_reference = $result->reference;
            $order->refunded_at = now();
            $order->refund_reason = $reason;

            if ($order->refunded_cents >= $order->total_cents) {
                $order->payment_status = Order::STATUS_REFUNDED;
                $order->token_expires_at = now(); // letöltési link azonnal lejár
                if ($order->coupon_id) {
                    Coupon::whereKey($order->coupon_id)->where('used_count', '>', 0)->decrement('used_count');
                }
            }

            $order->save();
        });

        $isFull = $order->refunded_cents >= $order->total_cents;
        activity()->performedOn($order)->causedBy(auth()->user())
            ->log('Visszatérítés'.($isFull ? ' (teljes)' : ' (részleges)').': '.number_format($amount, 0, ',', ' ').' Ft'.($reason ? ' — '.$reason : ''));

        // Teljes visszatérítésnél a kiállított számlát sztornózzuk + a még ki nem
        // fizetett fotós-jutalékokat érvénytelenítjük.
        if ($isFull) {
            app(OrderFulfillment::class)->purge($order->fresh());
            app(PhotographerPayoutService::class)->reverseForOrder($order);

            if ($invoice = $order->normalInvoice()) {
                StornoInvoiceJob::dispatch($invoice->id);
            }
        }

        Mail::to($order->buyer_email)->send(new OrderRefundedMail($order->fresh(), $amount));
    }

    /**
     * Fizetve jeloli a rendelest, kiadja a letoltesi tokent, novelu a kupon
     * felhasznalas-szamlalojat, es elkuldi a visszaigazolo e-mailt.
     * Idempotens: mar fizetve jelolt rendelesen nem csinal semmit (webhook +
     * sikeres-oldal szinkron ellenorzese is meghivhatja ugyanazt a rendelest).
     */
    public function markPaid(Order $order): void
    {
        if ($order->isPaid()) {
            return;
        }

        DB::transaction(function () use ($order) {
            $order->forceFill(['payment_status' => Order::STATUS_PAID])->save();
            $order->issueDownloadToken();

            if ($order->coupon_id) {
                Coupon::whereKey($order->coupon_id)->increment('used_count');
            }
        });

        activity()->performedOn($order)->log('Fizetés beérkezett'.($order->payment_provider ? ' ('.$order->payment_provider.')' : ''));

        if (app(InvoiceManager::class)->isConfigured() && app(InvoiceSettings::class)->autoIssue()) {
            IssueInvoiceJob::dispatch($order->id);
        }

        // A megvásárolt fájlok másolása a gyors, mindig elérhető kézbesítési
        // gyorsítótárba (a letöltés így nem függ az archív réteg elérhetőségétől).
        PrepareOrderDownloads::dispatch($order->id);

        // Fotós jutalék-tételek rögzítése (kifizetés-elszámoláshoz).
        app(PhotographerPayoutService::class)->recordForOrder($order->fresh()->load('media'));

        Mail::to($order->buyer_email)->send(new OrderConfirmationMail($order->fresh()));
    }
}
