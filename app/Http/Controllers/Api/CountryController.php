<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Aktiv orszagok listaja (nyilvanos, fooldali szuronek es admin dropdownoknak).
     */
    public function index(): JsonResponse
    {
        $countries = Country::query()
            ->where('active', true)
            ->orderBy('name_hu')
            ->get(['id', 'code', 'name_hu', 'name_en', 'flag_emoji']);

        return response()->json([
            'data' => $countries->map(fn (Country $country) => [
                'code' => $country->code,
                'name' => $country->name,
                'name_en' => $country->name_en,
                'flag_emoji' => $country->flag_emoji,
            ]),
        ]);
    }
}
