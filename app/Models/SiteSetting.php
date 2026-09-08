<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value'])]
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("site_setting:{$key}", 300, function () use ($key, $default) {
            return static::query()->find($key)?->value ?? $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("site_setting:{$key}");
    }
}
