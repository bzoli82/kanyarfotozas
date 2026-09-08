<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Sikertelen bejelentkezesi kiserletek naploja a superadmin dashboard
 * biztonsagi figyelmeztetes paneljehez (master.txt 9.1). Az IP cim hashelve
 * tarolodik (nem nyersen) — csak arra kell, hogy ugyanarrol a cimrol jovo
 * ismetelt probalkozasokat lehessen szamlalni, magat a cimet nem kell latni.
 */
#[Fillable(['email', 'ip_hash'])]
class FailedLoginAttempt extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attempt) {
            $attempt->created_at ??= now();
        });
    }
}
