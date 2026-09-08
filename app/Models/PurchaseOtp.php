<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A "Korábbi vásárlásaim" (/my-purchases) OTP-alapu e-mail verifikaciojahoz —
 * a nyers 6 jegyu kod SOHA nem tarolodik, csak sha256 hash. Lasd App\Services\PurchaseLookup.
 */
#[Fillable(['email_hash', 'otp_hash', 'expires_at', 'used_at'])]
class PurchaseOtp extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
