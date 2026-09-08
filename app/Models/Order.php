<?php

namespace App\Models;

use App\Services\SiteBranding;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'order_number', 'buyer_email', 'total_cents', 'payment_provider', 'payment_provider_reference',
    'payment_status', 'coupon_id', 'discount_cents', 'plate_consent',
    'payment_provider_order_ref', 'refunded_cents', 'refund_reference', 'refunded_at', 'refund_reason',
    'billing_name', 'billing_country', 'billing_zip', 'billing_city', 'billing_address', 'billing_tax_number',
    'fulfillment_status', 'fulfillment_prepared_at', 'fulfillment_attempts', 'fulfillment_error',
    'terms_accepted_at',
])]
class Order extends Model
{
    use HasFactory, LogsActivity;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_REFUNDED = 'refunded';

    public const TOKEN_MAX_USES = 5;

    protected static function booted(): void
    {
        // Ember-olvasható sorszám a rendelés létrejötte után (kell hozzá az id).
        static::created(function (self $order) {
            if (blank($order->order_number)) {
                $number = $order->generateOrderNumber();
                $order->newQuery()->whereKey($order->id)->update(['order_number' => $number]);
                $order->setAttribute('order_number', $number)->syncOriginalAttribute('order_number');
            }
        });
    }

    public function generateOrderNumber(): string
    {
        $prefix = 'ORD';

        try {
            $slug = preg_replace('/[^a-z0-9]/i', '', app(SiteBranding::class)->slug());
            $prefix = Str::upper(Str::substr($slug ?: 'ORD', 0, 3));
        } catch (\Throwable) {
            // konténer/DB nem elérhető (pl. migráció közben) — marad az alap prefix
        }

        return sprintf('%s-%d-%06d', $prefix, ($this->created_at ?? now())->year, $this->id);
    }

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'refunded_at' => 'datetime',
            'fulfillment_prepared_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'plate_consent' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Media, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'order_media')
            ->withPivot('id', 'price_cents')
            ->withTimestamps();
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function normalInvoice(): ?Invoice
    {
        return $this->invoices->firstWhere(fn (Invoice $i) => $i->type === Invoice::TYPE_NORMAL && $i->isIssued())
            ?? $this->invoices()->where('type', Invoice::TYPE_NORMAL)->where('status', Invoice::STATUS_ISSUED)->first();
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::STATUS_PAID;
    }

    public function isRefunded(): bool
    {
        return $this->payment_status === self::STATUS_REFUNDED;
    }

    /** Visszatéríthető-e: fizetve van és még nincs (teljesen) visszatérítve. */
    public function isRefundable(): bool
    {
        return $this->isPaid() && filled($this->payment_provider) && $this->refunded_cents < $this->total_cents;
    }

    /** A megvásárolt fájlok másolata készen áll-e a gyors `delivery` diskon. */
    public function deliveryReady(): bool
    {
        return $this->fulfillment_status === 'ready';
    }

    public function isTokenValid(): bool
    {
        return $this->download_token
            && $this->token_expires_at?->isFuture()
            && $this->download_token_uses < self::TOKEN_MAX_USES;
    }

    /**
     * 72 orara ervenyes UUID download tokent general es aktivalja a rendelest.
     */
    public function issueDownloadToken(): string
    {
        $token = (string) Str::uuid();

        $this->forceFill([
            'download_token' => $token,
            'token_expires_at' => now()->addHours(72),
            'download_token_uses' => 0,
        ])->save();

        return $token;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['payment_status', 'total_cents', 'refunded_cents'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
