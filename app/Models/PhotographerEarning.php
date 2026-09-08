<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Egy fotos jutaleka egyetlen megvasarolt media (order_media sor) utan. A
 * `share_percent` es a `gross_cents` az elszamolas rogzitesekor snapshot-olodik,
 * hogy a kesobbi jutalek-modositas ne irja at a mar konyvelt osszegeket.
 */
#[Fillable([
    'photographer_id', 'order_id', 'media_id', 'order_media_id',
    'gross_cents', 'share_percent', 'amount_cents', 'status', 'payout_id',
    'earned_at', 'reversed_at',
])]
class PhotographerEarning extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_REVERSED = 'reversed';

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @return BelongsTo<PhotographerPayout, $this>
     */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(PhotographerPayout::class);
    }
}
