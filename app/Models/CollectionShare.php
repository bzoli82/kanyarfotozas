<?php

namespace App\Models;

use Database\Factories\CollectionShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Egy latogatoi kollekcio (wishlist) megosztasa rovid koddal (/collection/share/{token}) —
 * 7 nap lejarat. A kollekcio egyebkent csak localStorage-ban el (nincs regisztracio).
 */
#[Fillable(['share_token', 'media_ids', 'expires_at', 'view_count'])]
class CollectionShare extends Model
{
    /** @use HasFactory<CollectionShareFactory> */
    use HasFactory;

    public const TTL_DAYS = 7;

    protected function casts(): array
    {
        return [
            'media_ids' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CollectionShare $share) {
            $share->share_token ??= strtoupper(Str::random(8));
            $share->expires_at ??= now()->addDays(self::TTL_DAYS);
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
