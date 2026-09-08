<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['email', 'name', 'token', 'role', 'revenue_share_percent', 'invited_by', 'expires_at', 'accepted_at'])]
class Invitation extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation) {
            $invitation->token ??= Str::random(48);
            $invitation->expires_at ??= now()->addHours(48);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }

    public function isPending(): bool
    {
        return ! $this->isAccepted() && ! $this->isExpired();
    }

    /**
     * Uj tokent + 48 orás lejaratot general, hogy egy lejart meghivo ujra
     * elkuldheto legyen anelkul, hogy uj rekordot kellene letrehozni.
     */
    public function reissue(): void
    {
        $this->forceFill([
            'token' => Str::random(48),
            'expires_at' => now()->addHours(48),
        ])->save();
    }
}
