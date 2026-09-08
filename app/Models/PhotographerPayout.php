<?php

namespace App\Models;

use App\Services\SiteBranding;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Egy fotosnak teljesitett (vagy elokeszitett) kifizetes: a hozza tartozo
 * `PhotographerEarning` tetelek osszevonasa egy elszamolasi bizonylatba.
 */
#[Fillable([
    'photographer_id', 'payout_number', 'period_start', 'period_end',
    'gross_cents', 'amount_cents', 'media_count', 'status', 'method',
    'reference', 'note', 'paid_at', 'created_by',
])]
class PhotographerPayout extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PAID = 'paid';

    protected static function booted(): void
    {
        static::created(function (self $payout) {
            if (blank($payout->payout_number)) {
                $number = $payout->generatePayoutNumber();
                $payout->newQuery()->whereKey($payout->id)->update(['payout_number' => $number]);
                $payout->setAttribute('payout_number', $number)->syncOriginalAttribute('payout_number');
            }
        });
    }

    public function generatePayoutNumber(): string
    {
        $prefix = 'ORD';

        try {
            $slug = preg_replace('/[^a-z0-9]/i', '', app(SiteBranding::class)->slug());
            $prefix = Str::upper(Str::substr($slug ?: 'ORD', 0, 3));
        } catch (\Throwable) {
            // konténer/DB nem elérhető — marad az alap prefix
        }

        return sprintf('%s-KIF-%d-%04d', $prefix, ($this->created_at ?? now())->year, $this->id);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    /**
     * @return HasMany<PhotographerEarning, $this>
     */
    public function earnings(): HasMany
    {
        return $this->hasMany(PhotographerEarning::class, 'payout_id');
    }
}
