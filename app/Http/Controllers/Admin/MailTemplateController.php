<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\MailTemplates;
use App\Services\SiteBranding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

/**
 * A kimeno e-mailek testreszabhato szoveges blokkjainak (targy, cimsor, bevezeto,
 * zaro bekezdes, alairas) szerkesztofelulete — csak superadmin. A mailable-ok az
 * App\Services\MailTemplates-en at kerik le az ertekeket.
 */
class MailTemplateController extends Controller
{
    /** Elonezeti minta-ertekek a placeholder-ekhez (csak a feluleten). */
    private const SAMPLES = [
        'order_id' => '1042',
        'order_number' => 'KAN-2026-001042',
        'expires_at' => '2026. 09. 20. 18:00',
        'item_count' => '3',
        'name' => 'Kovács Béla',
        'email' => 'bela@example.com',
        'subject' => 'Kérdés a videókról',
        'role' => 'fotós',
        'invited_by' => 'Admin Anna',
        'period' => 'Heti',
        'from' => '2026. 09. 01.',
        'to' => '2026. 09. 07.',
    ];

    public function index(SiteBranding $branding): InertiaResponse
    {
        $samples = ['app_name' => $branding->name()] + self::SAMPLES;

        return Inertia::render('Admin/Settings/Mail', [
            'templates' => collect(MailTemplates::registry())->map(function (array $meta, string $key) use ($samples) {
                return [
                    'key' => $key,
                    'label' => $meta['label'],
                    'description' => $meta['description'],
                    'fields' => MailTemplates::raw($key),
                    'defaults' => $meta['defaults'],
                    'placeholders' => collect($meta['placeholders'])->map(fn ($label, $token) => [
                        'token' => ':'.$token,
                        'label' => $label,
                        'sample' => $samples[$token] ?? $token,
                    ])->values(),
                    'editableFields' => MailTemplates::FIELDS,
                ];
            })->values(),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        abort_unless(MailTemplates::exists($key), 404);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'heading' => ['required', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'outro' => ['nullable', 'string', 'max:2000'],
            'signature' => ['nullable', 'string', 'max:500'],
        ]);

        MailTemplates::update($key, $data);

        return back()->with('success', 'Az e-mail sablon elmentve.');
    }

    public function reset(string $key): RedirectResponse
    {
        abort_unless(MailTemplates::exists($key), 404);

        MailTemplates::reset($key);

        return back()->with('success', 'Az e-mail sablon visszaállítva az alapértelmezettre.');
    }
}
