<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name', 'email', 'password', 'role', 'bio', 'avatar_s3_key',
    'social_instagram', 'social_facebook', 'social_youtube',
    'revenue_share_percent', 'is_active', 'is_public',
    'report_weekly', 'report_monthly',
])]
#[Hidden(['password', 'remember_token', 'invitation_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, LogsActivity, Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PHOTOGRAPHER = 'photographer';

    public const ROLE_ORGANIZER = 'organizer';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'report_weekly' => 'boolean',
            'report_monthly' => 'boolean',
            'last_login_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /** Élesített kétfaktoros hitelesítés (titok megvan ÉS megerősítve). */
    public function hasTwoFactorEnabled(): bool
    {
        return filled($this->two_factor_secret) && $this->two_factor_confirmed_at !== null;
    }

    /**
     * Esemenyek, amiket ez a felhasznalo hozott letre (admin/superadmin).
     *
     * @return HasMany<Event, $this>
     */
    public function createdEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'created_by');
    }

    /**
     * Media, amit ez a fotos toltott fel.
     *
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class, 'photographer_id');
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPERADMIN, self::ROLE_ADMIN], true);
    }

    public function isPhotographer(): bool
    {
        return $this->role === self::ROLE_PHOTOGRAPHER;
    }

    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER;
    }

    /**
     * Esemenyek, amiket ez a felhasznalo SZERVEZ (esemeny-szervezo portal).
     *
     * @return HasMany<Event, $this>
     */
    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    protected static function booted(): void
    {
        // A `role` oszlop es a Spatie Permission szerepkor-hozzarendeles ket kulon
        // forras — enelkul konnyu elfelejteni az assignRole()-t uj user letrehozasakor,
        // es a `role:` route middleware csendben 403-at adna. Automatikusan szinkronban tartjuk.
        static::saved(function (User $user) {
            if (($user->wasRecentlyCreated || $user->wasChanged('role')) && $user->role) {
                $user->syncRoles([$user->role]);
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'role', 'revenue_share_percent', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
