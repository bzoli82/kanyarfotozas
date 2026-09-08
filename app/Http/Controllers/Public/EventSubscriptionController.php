<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\EventSubscription;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Helyszin-ertesito leiratkozas (EPIC-17) — minden ertesito e-mailben szerepel
 * egy egyedi tokenes link. Egy kattintassal, megerosites nelkul torol.
 */
class EventSubscriptionController extends Controller
{
    public function destroy(string $token): Response
    {
        $subscription = EventSubscription::query()->where('unsubscribe_token', $token)->first();

        $location = $subscription?->location;
        $subscription?->delete();

        return Inertia::render('Public/Unsubscribed', [
            'location' => $location,
        ]);
    }
}
