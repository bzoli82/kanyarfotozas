<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'label', 'sort_order', 'is_visible', 'is_locked', 'config'])]
class LandingSection extends Model
{
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_locked' => 'boolean',
            'config' => 'array',
        ];
    }
}
