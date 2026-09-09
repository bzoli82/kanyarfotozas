<?php

use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CollectionController;
use App\Http\Controllers\Api\CountryController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\DataSyncController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\PhotographerController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Public\BarionCallbackController;
use App\Http\Controllers\Public\SimplePayIpnController;
use App\Http\Controllers\Public\StripeWebhookController;
use App\Http\Controllers\Public\WebSchedulerController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Nyilvanos vegpontok
Route::get('/countries', [CountryController::class, 'index'])->name('api.countries.index');
Route::get('/events', [EventController::class, 'index'])->name('api.events.index');
Route::get('/events/locations', [EventController::class, 'locations'])->name('api.events.locations');
Route::get('/events/{event}/media', [EventController::class, 'media'])->name('api.events.media');
Route::get('/events/{event}/media/nearby', [EventController::class, 'nearby'])->name('api.events.media.nearby');
Route::get('/events/{event}/media/histogram', [EventController::class, 'histogram'])->name('api.events.media.histogram');
Route::get('/photographers', [PhotographerController::class, 'index'])->name('api.photographers.index');
Route::get('/cart', [CartController::class, 'show'])->name('api.cart.show');
Route::post('/coupon/validate', [CouponController::class, 'validateCode'])->name('api.coupon.validate');

// EPIC-17 — kollekcio / wishlist + esemeny-ertesito feliratkozas
Route::post('/collection/track', [CollectionController::class, 'track'])->middleware('throttle:60,1')->name('api.collection.track');
Route::post('/collection/share', [CollectionController::class, 'share'])->middleware('throttle:20,1')->name('api.collection.share');
Route::post('/subscriptions', [SubscriptionController::class, 'store'])->middleware('throttle:10,1')->name('api.subscriptions.store');

// „Webes utemezo" — egy kulso cron szolgaltatas percenkent meghivja a titkos URL-t
// (ha a szerveren nincs mod rendes cront allitani). Ld. App\Services\WebScheduler.
Route::match(['get', 'post'], '/ops/scheduler/{token}', WebSchedulerController::class)
    ->middleware('throttle:30,1')
    ->name('api.ops.scheduler');

// Eles ↔ helyi adat-szinkron FORRAS vegpontjai — a helyi gep hivja `Bearer <token>`-nel.
// Ld. App\Services\DataSync. Kikapcsolt forras / rossz kulcs = 404.
Route::get('/sync/manifest', [DataSyncController::class, 'manifest'])
    ->middleware('throttle:20,1')
    ->name('api.sync.manifest');
Route::get('/sync/database', [DataSyncController::class, 'database'])
    ->middleware('throttle:6,1')
    ->name('api.sync.database');

// Fizetesi szolgaltatok szerver-szerver ertesitesei — nem session-alapu, nincs CSRF.
Route::middleware('payment.settings')->group(function () {
    Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])->name('api.stripe.webhook');
    Route::post('/simplepay/ipn', [SimplePayIpnController::class, 'handle'])->name('api.simplepay.ipn');
    Route::match(['get', 'post'], '/barion/callback', [BarionCallbackController::class, 'handle'])->name('api.barion.callback');
});
