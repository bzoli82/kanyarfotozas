<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['email', 'type', 'status', 'token', 'note', 'verified_at', 'completed_at', 'handled_by'])]
class DataRequest extends Model
{
    use HasFactory;

    public const TYPE_EXPORT = 'export';

    public const TYPE_DELETE = 'delete';

    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->token ??= (string) Str::uuid();
        });
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }
}
