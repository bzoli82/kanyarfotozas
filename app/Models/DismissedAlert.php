<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Egy dashboard proaktiv figyelmeztetes elrejtese egy admin altal — a
 * `dismissed_until` idopontig nem jelenik meg, utana (ha a feltetel meg all)
 * ujra lathato. Lasd App\Services\ProactiveAlerts.
 */
#[Fillable(['alert_key', 'dismissed_by', 'dismissed_until'])]
class DismissedAlert extends Model
{
    protected function casts(): array
    {
        return [
            'dismissed_until' => 'datetime',
        ];
    }
}
