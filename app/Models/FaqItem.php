<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'question_hu', 'answer_hu', 'question_en', 'answer_en',
    'category', 'sort_order', 'active', 'is_homepage',
])]
class FaqItem extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'is_homepage' => 'boolean',
        ];
    }
}
