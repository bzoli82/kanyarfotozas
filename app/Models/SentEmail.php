<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Kimenő e-mailek naplója (a `LogSentEmail` listener tölti a `MessageSent`
 * eseményből) — „a vevő megkapta-e a letöltő linket" típusú kérdésekhez.
 * Csak sikeres küldés kerül be; a régi sorokat napi ütemezett takarítás törli.
 */
#[Fillable(['recipient', 'subject', 'mailable', 'created_at'])]
class SentEmail extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
