<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Napi galeria-megtekintes rollup esemenyenkent (event_id + viewed_on paronkent
 * egy sor). A dashboard konverzios tolcser top-of-funnel lepcsoje.
 */
#[Fillable(['event_id', 'viewed_on', 'count'])]
class EventView extends Model
{
    protected function casts(): array
    {
        return [
            'viewed_on' => 'date',
            'count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
