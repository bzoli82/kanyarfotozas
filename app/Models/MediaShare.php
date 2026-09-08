<?php

namespace App\Models;

use Database\Factories\MediaShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Egy megvasarolt media publikus, VIZJELES megosztasa (/share/{token}) — 30 nap
 * lejarattal. Organikus marketing + social proof. Lasd App\Services\MediaShareService.
 */
#[Fillable(['media_id', 'order_id', 'share_token', 'platform', 'expires_at', 'view_count'])]
class MediaShare extends Model
{
    /** @use HasFactory<MediaShareFactory> */
    use HasFactory;

    public const TTL_DAYS = 30;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MediaShare $share) {
            $share->share_token ??= (string) Str::uuid();
            $share->expires_at ??= now()->addDays(self::TTL_DAYS);
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
