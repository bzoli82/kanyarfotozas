<?php

namespace App\Models;

use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

#[Fillable([
    'country_id', 'name', 'location', 'latitude', 'longitude',
    'event_date', 'starts_at', 'ends_at', 'status', 'featured_until', 'created_by',
    'photo_price_cents', 'video_price_cents', 'organizer_id', 'organizer_share_percent',
])]
#[ObservedBy(EventObserver::class)]
class Event extends Model
{
    use HasFactory, HasSlug, LogsActivity;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ANNOUNCED = 'announced';

    public const STATUS_LIVE = 'live';

    public const STATUS_ARCHIVED = 'archived';

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'event_date' => 'date',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'featured_until' => 'datetime',
            'photo_price_cents' => 'integer',
            'video_price_cents' => 'integer',
            'organizer_share_percent' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Az esemeny egy adott tipusu (photo|video) mediajanak ara. Ha az esemenyen
     * nincs explicit ar, a `base_price_huf` beallitasra esik vissza (vegso soron 1490).
     */
    public function priceFor(string $type): int
    {
        $explicit = $type === Media::TYPE_VIDEO ? $this->video_price_cents : $this->photo_price_cents;

        return (int) ($explicit ?? SiteSetting::get('base_price_huf', 1490));
    }

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(function (Event $event) {
                return $event->name.'-'.$event->location.'-'.optional($event->event_date)->format('Y-m-d');
            })
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * @return BelongsTo<Country, $this>
     */
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Media, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    /**
     * @return HasMany<EventView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(EventView::class);
    }

    /**
     * Napi galeria-megtekintes szamlalo atomi novelese (fire-and-forget a
     * publikus galeria oldalon). A dashboard konverzios tolcser top-of-funnel
     * lepcsoje ebbol jon — kulso analitika nelkul. Postgres upsert.
     */
    public function recordGalleryView(): void
    {
        try {
            DB::statement(
                'INSERT INTO event_views (event_id, viewed_on, count, created_at, updated_at)
                 VALUES (?, CURRENT_DATE, 1, now(), now())
                 ON CONFLICT (event_id, viewed_on)
                 DO UPDATE SET count = event_views.count + 1, updated_at = now()',
                [$this->id],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'location', 'status', 'starts_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * `cover_thumbnail_s3_key` pszeudo-oszlop: az esemeny elso kesz mediajanak
     * thumbnail-je — a listaoldalak/terkep boritokepehez. Additiv (addSelect),
     * ezert nyugodtan hasznalhato withCount()/selectRaw() mellett is.
     */
    public function scopeWithCoverThumbnail(Builder $query): Builder
    {
        if (empty($query->getQuery()->columns)) {
            $query->select('events.*');
        }

        return $query->addSelect(['cover_thumbnail_s3_key' => Media::query()
            ->select('thumbnail_s3_key')
            ->whereColumn('event_id', 'events.id')
            ->where('status', Media::STATUS_READY)
            ->orderBy('id')
            ->limit(1),
        ]);
    }
}
