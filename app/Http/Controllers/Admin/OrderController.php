<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OrderConfirmationMail;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Order;
use App\Services\CheckoutService;
use App\Services\Invoicing\InvoiceException;
use App\Services\Invoicing\InvoiceManager;
use App\Services\MediaStorage;
use App\Services\Payments\PaymentException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\Activitylog\Models\Activity;

/**
 * Rendeléskezelő (admin + superadmin): lista/keresés/szűrés + részletek +
 * visszatérítés (Stripe / SimplePay) + letöltő link újraküldése.
 */
class OrderController extends Controller
{
    public function index(Request $request): InertiaResponse
    {
        $statuses = [Order::STATUS_PENDING, Order::STATUS_PAID, Order::STATUS_FAILED, Order::STATUS_REFUNDED];

        $filters = [
            'q' => $request->string('q')->value() ?: null,
            'status' => in_array($request->query('status'), $statuses, true) ? $request->query('status') : null,
        ];

        $orders = Order::query()
            ->withCount('media')
            ->when($filters['q'], fn ($query, $q) => $query->where(function ($sub) use ($q) {
                $sub->where('order_number', 'ILIKE', "%{$q}%")
                    ->orWhere('buyer_email', 'ILIKE', "%{$q}%")
                    ->orWhere('payment_provider_reference', 'ILIKE', "%{$q}%")
                    ->orWhere('id', ctype_digit($q) ? (int) $q : 0);
            }))
            ->when($filters['status'], fn ($query, $status) => $query->where('payment_status', $status))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Order $order) => $this->summary($order));

        return Inertia::render('Admin/Orders/Index', [
            'orders' => $orders,
            'filters' => $filters,
            'statuses' => $statuses,
        ]);
    }

    public function show(Order $order, InvoiceManager $invoicing): InertiaResponse
    {
        $order->load(['media:id,event_id,type', 'media.event:id,name', 'coupon:id,code', 'invoices']);

        return Inertia::render('Admin/Orders/Show', [
            'order' => [
                ...$this->summary($order),
                'discount_cents' => $order->discount_cents,
                'bulk_discount_cents' => $order->bulk_discount_cents,
                'coupon' => $order->coupon?->code,
                'plate_consent' => $order->plate_consent,
                'payment_provider_reference' => $order->payment_provider_reference,
                'refund_reference' => $order->refund_reference,
                'refund_reason' => $order->refund_reason,
                'download_token_uses' => $order->download_token_uses,
                'token_expires_at' => $order->token_expires_at?->toIso8601String(),
                'billing' => array_filter([
                    'name' => $order->billing_name,
                    'country' => $order->billing_country,
                    'zip' => $order->billing_zip,
                    'city' => $order->billing_city,
                    'address' => $order->billing_address,
                    'tax_number' => $order->billing_tax_number,
                ]),
                'items' => $order->media->map(fn (Media $m) => [
                    'id' => $m->id,
                    'type' => $m->type,
                    'event' => $m->event?->name,
                    'price_cents' => $m->pivot->price_cents,
                ]),
                'timeline' => $this->timeline($order),
            ],
            'invoicing' => [
                'configured' => $invoicing->isConfigured(),
                'invoices' => $order->invoices->sortBy('id')->map(fn (Invoice $i) => [
                    'id' => $i->id,
                    'type' => $i->type,
                    'number' => $i->number,
                    'status' => $i->status,
                    'error' => $i->error,
                    'has_pdf' => filled($i->pdf_path),
                    'issued_at' => $i->issued_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    public function issueInvoice(Order $order, InvoiceManager $invoicing): RedirectResponse
    {
        abort_unless($order->isPaid(), 422);

        if (! $invoicing->isConfigured()) {
            return back()->with('error', 'Nincs beállítva számlázó — /admin/settings/critical.');
        }

        try {
            $invoicing->issueFor($order);
        } catch (InvoiceException $e) {
            return back()->with('error', 'A számla kiállítása nem sikerült: '.$e->getMessage());
        }

        return back()->with('success', 'Számla kiállítva.');
    }

    public function stornoInvoice(Invoice $invoice, InvoiceManager $invoicing): RedirectResponse
    {
        try {
            $invoicing->stornoFor($invoice);
        } catch (InvoiceException $e) {
            return back()->with('error', 'A sztornó nem sikerült: '.$e->getMessage());
        }

        return back()->with('success', 'Sztornó számla kiállítva.');
    }

    public function invoicePdf(Invoice $invoice): Response
    {
        abort_unless(filled($invoice->pdf_path), 404);

        $contents = Storage::disk(MediaStorage::STAGING)->get($invoice->pdf_path);
        abort_if($contents === null, 404);

        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="szamla-'.($invoice->number ?: $invoice->id).'.pdf"',
        ]);
    }

    /**
     * A rendelés eseménynaplója (Spatie ActivityLog) — nyomonkövetés az admin részletnézetben.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(Order $order): array
    {
        $generic = ['created' => 'Rendelés létrehozva', 'updated' => 'Rendelés módosítva', 'deleted' => 'Rendelés törölve'];

        return Activity::query()
            ->where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)
            ->with('causer:id,name')
            ->orderBy('id')
            ->get()
            ->map(fn (Activity $a) => [
                'id' => $a->id,
                'description' => $generic[$a->description] ?? $a->description,
                'causer' => $a->causer?->name ?? 'rendszer',
                'created_at' => $a->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function refund(Request $request, Order $order, CheckoutService $checkout): RedirectResponse
    {
        $data = $request->validate([
            'amount_cents' => ['nullable', 'integer', 'min:1', 'max:'.max(1, $order->total_cents - $order->refunded_cents)],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $checkout->refund($order, $data['amount_cents'] ?? null, $data['reason'] ?? null);
        } catch (PaymentException $e) {
            return back()->with('error', 'A visszatérítés nem sikerült: '.$e->getMessage());
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Visszatérítés elindítva.');
    }

    public function resendEmail(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->isPaid() && $order->download_token, 422);

        Mail::to($order->buyer_email)->send(new OrderConfirmationMail($order));

        activity()->performedOn($order)->causedBy($request->user())->log('Letöltő e-mail újraküldve');

        return back()->with('success', 'A letöltő e-mail újraküldve.');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'buyer_email' => $order->buyer_email,
            'total_cents' => $order->total_cents,
            'refunded_cents' => $order->refunded_cents,
            'payment_status' => $order->payment_status,
            'payment_provider' => $order->payment_provider,
            'media_count' => $order->media_count ?? $order->media()->count(),
            'is_refundable' => $order->isRefundable(),
            'created_at' => $order->created_at?->toIso8601String(),
            'refunded_at' => $order->refunded_at?->toIso8601String(),
        ];
    }
}
