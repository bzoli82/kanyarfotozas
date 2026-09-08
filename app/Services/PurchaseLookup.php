<?php

namespace App\Services;

use App\Models\Order;
use App\Models\PurchaseOtp;
use Illuminate\Support\Collection;

/**
 * "Korábbi vásárlásaim" (/my-purchases) — OTP-alapu e-mail verifikacio (Magic Link
 * helyett 6 jegyu kod), majd az adott e-mail cimhez kotodo, fizetett rendelesek
 * listazasa. A nyers OTP-t sose taroljuk, csak sha256 hasht.
 */
class PurchaseLookup
{
    public const OTP_TTL_MINUTES = 10;

    /** Orankent max ennyi OTP-keres egy e-mail cimre. */
    public const MAX_REQUESTS_PER_HOUR = 3;

    public function hashEmail(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    public function hashOtp(string $otp): string
    {
        return hash('sha256', $otp);
    }

    public function isRateLimited(string $email): bool
    {
        return PurchaseOtp::query()
            ->where('email_hash', $this->hashEmail($email))
            ->where('created_at', '>=', now()->subHour())
            ->count() >= self::MAX_REQUESTS_PER_HOUR;
    }

    /**
     * Uj OTP-t general, elmenti (hashelve), es visszaadja a NYERS kodot (az
     * e-mailhez). Nem ellenorzi, hogy van-e egyaltalan rendeles az e-mailhez —
     * igy nem szivarog ki, hogy egy cim vasarolt-e (enumeration vedelem).
     */
    public function issueOtp(string $email): string
    {
        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        PurchaseOtp::query()->create([
            'email_hash' => $this->hashEmail($email),
            'otp_hash' => $this->hashOtp($otp),
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES),
        ]);

        return $otp;
    }

    public function verify(string $email, string $otp): bool
    {
        $record = PurchaseOtp::query()
            ->where('email_hash', $this->hashEmail($email))
            ->where('otp_hash', $this->hashOtp($otp))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $record) {
            return false;
        }

        $record->forceFill(['used_at' => now()])->save();

        return true;
    }

    /**
     * A megadott e-mail cimhez kotodo, fizetett rendelesek — a /my-purchases lista.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function ordersFor(string $email): Collection
    {
        return Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereRaw('LOWER(buyer_email) = ?', [mb_strtolower(trim($email))])
            ->with(['media.event:id,name,slug'])
            ->latest('created_at')
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'created_at' => $order->created_at->toIso8601String(),
                'total_cents' => $order->total_cents,
                'media_count' => $order->media->count(),
                'events' => $order->media->pluck('event.name')->filter()->unique()->values(),
                'thumbnails' => $order->media->take(4)->pluck('thumbnail_s3_key')->filter()->values(),
                'token_active' => $order->isTokenValid(),
                'token_expires_at' => $order->token_expires_at?->toIso8601String(),
                'download_url' => $order->isTokenValid() ? route('public.download.show', $order->download_token) : null,
            ]);
    }

    public function resendDownloadLink(string $email, int $orderId): ?Order
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->where('payment_status', Order::STATUS_PAID)
            ->whereRaw('LOWER(buyer_email) = ?', [mb_strtolower(trim($email))])
            ->first();

        if (! $order) {
            return null;
        }

        if (! $order->isTokenValid()) {
            $order->issueDownloadToken();
        }

        return $order->fresh();
    }

    public function pruneExpired(): int
    {
        return PurchaseOtp::query()->where('created_at', '<', now()->subDay())->delete();
    }
}
