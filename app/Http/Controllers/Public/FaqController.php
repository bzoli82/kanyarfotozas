<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\FaqItem;
use Illuminate\Support\Facades\App;
use Inertia\Inertia;
use Inertia\Response;

class FaqController extends Controller
{
    private const CATEGORY_LABELS = [
        'general' => ['hu' => 'Általános', 'en' => 'General'],
        'payment' => ['hu' => 'Fizetés', 'en' => 'Payment'],
        'download' => ['hu' => 'Letöltés', 'en' => 'Download'],
        'video' => ['hu' => 'Videó', 'en' => 'Video'],
    ];

    public function index(): Response
    {
        $locale = App::getLocale() === 'en' ? 'en' : 'hu';

        $items = FaqItem::query()
            ->where('active', true)
            ->orderBy('category')
            ->orderBy('sort_order')
            ->get(['id', 'question_hu', 'answer_hu', 'question_en', 'answer_en', 'category']);

        return Inertia::render('Info/Faq', [
            'categories' => $items
                ->groupBy('category')
                ->map(fn ($group, $key) => [
                    'key' => $key,
                    'label' => self::CATEGORY_LABELS[$key][$locale] ?? ucfirst($key),
                    'items' => $group->map(fn (FaqItem $item) => [
                        'id' => $item->id,
                        'question' => $locale === 'en' ? ($item->question_en ?: $item->question_hu) : $item->question_hu,
                        'answer' => $locale === 'en' ? ($item->answer_en ?: $item->answer_hu) : $item->answer_hu,
                    ])->values(),
                ])
                ->values(),
        ]);
    }
}
