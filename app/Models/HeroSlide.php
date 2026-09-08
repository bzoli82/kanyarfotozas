<?php

namespace App\Models;

use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A fooldali hero szekcio hattereben egymasba animalodo kepek / videok — superadmin
 * tolti fel oket a /admin/settings/hero feluleten, a feldolgozott media a `public` diskon.
 */
#[Fillable([
    'type', 'image_path', 'video_path', 'poster_path', 'original_filename',
    'width', 'height', 'duration_seconds', 'sort_order', 'is_active',
])]
class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use HasFactory;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function isVideo(): bool
    {
        return $this->type === self::TYPE_VIDEO;
    }

    /**
     * @param  Builder<HeroSlide>  $query
     */
    public function scopeActiveOrdered(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }
}
