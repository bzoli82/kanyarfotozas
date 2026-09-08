<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PlateRecognitionSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * A rendszamfelismero (OCR) rendszer ki/be kapcsolasa es finomhangolasa —
 * csak superadmin. A kepfeldolgozo pipeline az App\Services\PlateRecognitionSettings-en
 * at kerdezi le ezeket.
 */
class PlateRecognitionSettingsController extends Controller
{
    public function index(PlateRecognitionSettings $settings): InertiaResponse
    {
        return Inertia::render('Admin/Settings/PlateRecognition', [
            'settings' => $settings->toArray(),
            'confidenceRange' => [
                'min' => PlateRecognitionSettings::MIN_CONFIDENCE_FLOOR,
                'max' => PlateRecognitionSettings::MIN_CONFIDENCE_CEILING,
            ],
            'providers' => PlateRecognitionSettings::PROVIDERS,
        ]);
    }

    public function update(Request $request, PlateRecognitionSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'mode' => ['required', Rule::in(PlateRecognitionSettings::MODES)],
            'min_confidence' => [
                'required',
                'integer',
                'min:'.PlateRecognitionSettings::MIN_CONFIDENCE_FLOOR,
                'max:'.PlateRecognitionSettings::MIN_CONFIDENCE_CEILING,
            ],
            'provider' => ['nullable', Rule::in(PlateRecognitionSettings::PROVIDERS)],
            'api_key' => ['nullable', 'string', 'max:2000'],
            'clear_api_key' => ['sometimes', 'boolean'],
        ]);

        if ($request->boolean('clear_api_key')) {
            $settings->clearApiKey();
        }

        $settings->update(
            $data['enabled'],
            $data['mode'],
            $data['min_confidence'],
            $data['provider'] ?? null,
            $data['api_key'] ?? null,
        );

        return back()->with('success', 'Rendszámfelismerés beállítások elmentve.');
    }
}
