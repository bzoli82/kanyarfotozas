<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['email', 'location', 'country_id', 'unsubscribe_token', 'last_notified_at'])]
class EventSubscription extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'last_notified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventSubscription $subscription) {
            $subscription->unsubscribe_token ??= (string) Str::uuid();
        });
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
}
