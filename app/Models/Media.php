<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable([
    'event_id', 'photographer_id', 'type', 'content_hash',
    'original_s3_key', 'watermarked_s3_key', 'thumbnail_s3_key',
    'download_jpeg_s3_key', 'download_webp_s3_key',
    'preview_sprite_s3_key', 'preview_sprite_interval', 'hls_playlist_s3_key', 'duration_seconds',
    'shot_at', 'width', 'height', 'price_cents', 'status',
    'license_plate', 'license_plate_bbox', 'license_plate_blurred', 'license_plate_confidence', 'license_plate_status',
    'original_storage', 'archived_at', 'archive_attempts', 'archive_error', 'import_source_path',
])]
class Media extends Model
{
    use HasFactory, LogsActivity;

    public const TYPE_PHOTO = 'photo';

    public const TYPE_VIDEO = 'video';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_HIDDEN = 'hidden';

    /** Az eredeti + letölthető fájlok a lokális `local` (staging) diskon vannak. */
    public const STORAGE_LOCAL = 'local';

    /**
     * Az eredeti + letölthető fájlok az archív diskre kerültek (SFTP NAS vagy
     * élesben Cloudflare R2 — lásd `config/media.php` `disks.archive` + `App\Services\MediaStorage`).
     * A `'nas'` érték történeti okból marad meg (a meglévő sorok migrálás nélkül működnek).
     */
    public const STORAGE_NAS = 'nas';

    public const PLATE_PENDING = 'pending';

    public const PLATE_NONE = 'none';

    public const PLATE_DETECTED = 'detected';

    public const PLATE_UNIDENTIFIABLE = 'unidentifiable';

    /**
     * A NAS-ra archivalando "nagy" fajl mezok: db oszlop => [fajlnev-utotag, kiterjesztes].
     * A kiterjesztes null eseten az eredeti feltoltott fajl kiterjeseset orzi meg.
     */
    public const ARCHIVABLE_FIELDS = [
        'original_s3_key' => ['suffix' => 'original', 'extension' => null],
        'download_jpeg_s3_key' => ['suffix' => 'download', 'extension' => 'jpg'],
        'download_webp_s3_key' => ['suffix' => 'download', 'extension' => 'webp'],
    ];

    public const MAX_ARCHIVE_ATTEMPTS = 5;

    protected $table = 'media';

    protected function casts(): array
    {
        return [
            'shot_at' => 'datetime',
            'license_plate_bbox' => 'array',
            'license_plate_blurred' => 'boolean',
            'license_plate_confidence' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function photographer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'photographer_id');
    }

    /**
     * @return BelongsToMany<Order, $this>
     */
    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_media')
            ->withPivot('price_cents')
            ->withTimestamps();
    }

    public function isPhoto(): bool
    {
        return $this->type === self::TYPE_PHOTO;
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    public function isReady(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'price_cents'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
