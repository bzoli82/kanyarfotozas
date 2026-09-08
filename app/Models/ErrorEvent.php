<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Egy kezeletlen kivétel-csoport (ujjlenyomat szerint). Az App\Services\ErrorReporter
 * hozza létre / számlálja; a superadmin a /admin/errors oldalon látja és zárja le.
 */
#[Fillable([
    'fingerprint', 'exception_class', 'message', 'file', 'line', 'url', 'method',
    'count', 'first_seen_at', 'last_seen_at', 'resolved_at',
])]
class ErrorEvent extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
