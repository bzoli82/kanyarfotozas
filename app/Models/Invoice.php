<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id', 'provider', 'type', 'storno_of', 'external_id', 'number',
    'gross_cents', 'pdf_path', 'status', 'error', 'attempts', 'issued_at',
])]
class Invoice extends Model
{
    use HasFactory;

    public const TYPE_NORMAL = 'normal';

    public const TYPE_STORNO = 'storno';

    public const STATUS_ISSUED = 'issued';

    public const STATUS_FAILED = 'failed';

    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isIssued(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }

    public function isStorno(): bool
    {
        return $this->type === self::TYPE_STORNO;
    }
}
