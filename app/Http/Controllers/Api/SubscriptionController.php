<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\EventSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Helyszin-ertesito feliratkozas (EPIC-17): a latogato megadja az e-mail cimet
 * egy adott helyszinhez, es amikor ott uj esemeny kepei elerhetok lesznek
 * (status: live), automatikus e-mailt kap. Regisztracio nincs, double opt-in
 * nem szukseges (GDPR jogalap: kifejezett kerelem).
 */
class SubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'size:2'],
        ]);

        $countryId = ! empty($data['country_code'])
            ? Country::query()->where('code', strtoupper($data['country_code']))->value('id')
            : null;

        $subscription = EventSubscription::query()->firstOrCreate(
            [
                'email' => mb_strtolower($data['email']),
                'location' => $data['location'],
            ],
            ['country_id' => $countryId],
        );

        return response()->json([
            'message' => 'Feliratkoztál — értesítünk, amint új felvétel érhető el ezen a helyszínen.',
            'unsubscribe_token' => $subscription->unsubscribe_token,
        ], $subscription->wasRecentlyCreated ? 201 : 200);
    }
}
