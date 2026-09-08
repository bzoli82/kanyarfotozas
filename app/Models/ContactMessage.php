<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'email', 'subject', 'message', 'contact_type', 'photographer_id', 'event_id', 'status', 'last_reply_at'])]
class ContactMessage extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_RESOLVED = 'resolved';

    public const TYPE_SUPPORT = 'support';

    public const TYPE_PHOTOGRAPHER = 'photographer';

    public const TYPE_OTHER = 'other';

    protected function casts(): array
    {
        return [
            'last_reply_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ContactReply, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ContactReply::class)->orderBy('created_at');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
