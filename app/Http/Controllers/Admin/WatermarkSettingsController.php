<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ImageProcessingService;
use App\Services\WatermarkSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class WatermarkSettingsController extends Controller
{
    /**
     * Vizjel-beallitasok (szoveg, betutipus, meret, suruseg) — csak superadmin,
     * mind a foto-, mind a videopipeline (ImageProcessingService/VideoProcessingService)
     * ugyanezt hasznalja.
     */
    public function index(WatermarkSettings $watermark): InertiaResponse
    {
        return Inertia::render('Admin/Settings/Watermark', [
            'settings' => [
                'text' => $watermark->text(),
                'font' => $watermark->fontKey(),
                'size' => $watermark->size(),
                'density' => $watermark->density(),
            ],
            'fonts' => collect(WatermarkSettings::FONTS)->map(fn ($font, $key) => [
                'value' => $key,
                'label' => $font['label'],
            ])->values(),
        ]);
    }

    public function update(Request $request, WatermarkSettings $watermark): RedirectResponse
    {
        $data = $request->validate([
            'text' => ['required', 'string', 'max:60'],
            'font' => ['required', Rule::in(array_keys(WatermarkSettings::FONTS))],
            'size' => ['required', 'integer', 'min:10', 'max:60'],
            'density' => ['required', 'integer', Rule::in(array_keys(WatermarkSettings::DENSITY_SPACING))],
        ]);

        $watermark->update($data['text'], $data['font'], $data['size'], $data['density']);

        return back()->with('success', 'Vízjel beállítások elmentve.');
    }

    /**
     * Elo elonezet egy minta-kepen, a MEG EL NEM MENTETT urlap-ertekekkel —
     * igy az admin latja a hatast mielott menti.
     */
    public function preview(Request $request, ImageProcessingService $processor): Response
    {
        $data = Validator::make($request->all(), [
            'text' => ['required', 'string', 'max:60'],
            'font' => ['required', Rule::in(array_keys(WatermarkSettings::FONTS))],
            'size' => ['required', 'integer', 'min:10', 'max:60'],
            'density' => ['required', 'integer', Rule::in(array_keys(WatermarkSettings::DENSITY_SPACING))],
        ])->validate();

        $fontPath = resource_path('fonts/'.WatermarkSettings::FONTS[$data['font']]['file']);
        $spacing = WatermarkSettings::DENSITY_SPACING[$data['density']];

        $samplePath = public_path('images/watermark-preview-sample.jpg');
        $webp = $processor->previewWithSettings($samplePath, $data['text'], $fontPath, (int) $data['size'], $spacing);

        return response($webp, 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'no-store',
        ]);
    }
}
