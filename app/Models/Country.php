<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\App;

#[Fillable(['code', 'name_hu', 'name_en', 'flag_emoji', 'active'])]
class Country extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /**
     * Az aktualis nyelvnek megfelelo orszagnev — EN alatt a name_en-re esik vissza
     * name_hu-ra, ha nincs angol forditas. A relaciot ehhez name_hu ES name_en
     * oszloppal kell betolteni.
     */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => App::getLocale() === 'en'
            ? ($this->name_en ?: $this->name_hu)
            : $this->name_hu);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
