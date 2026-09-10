<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\BrandingSettingsController;
use App\Http\Controllers\Admin\CriticalSettingsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DataRequestController as AdminDataRequestController;
use App\Http\Controllers\Admin\DataSyncController;
use App\Http\Controllers\Admin\ErrorEventController;
use App\Http\Controllers\Admin\EventController;
use App\Http\Controllers\Admin\ForensicController;
use App\Http\Controllers\Admin\GeoController;
use App\Http\Controllers\Admin\GuideController;
use App\Http\Controllers\Admin\HeroSlideController;
use App\Http\Controllers\Admin\LegalSettingsController;
use App\Http\Controllers\Admin\LocationSearchController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\MailTemplateController;
use App\Http\Controllers\Admin\MaintenanceModeController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaImportController;
use App\Http\Controllers\Admin\MediaUploadController;
use App\Http\Controllers\Admin\MessageController as AdminMessageController;
use App\Http\Controllers\Admin\OrderController;
use App\Http\Controllers\Admin\OrganizerPayoutController;
use App\Http\Controllers\Admin\PageImageLibraryController;
use App\Http\Controllers\Admin\PayoutController;
use App\Http\Controllers\Admin\PhotographerController;
use App\Http\Controllers\Admin\PlateRecognitionSettingsController;
use App\Http\Controllers\Admin\PricingSettingsController;
use App\Http\Controllers\Admin\SecuritySettingsController;
use App\Http\Controllers\Admin\SeoSettingsController;
use App\Http\Controllers\Admin\SocialSettingsController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\StorageSettingsController;
use App\Http\Controllers\Admin\ThemeSettingsController;
use App\Http\Controllers\Admin\WatermarkSettingsController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\InvitationController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\LlmsController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MobileUploadController;
use App\Http\Controllers\Organizer\DashboardController as OrganizerDashboardController;
use App\Http\Controllers\Photographer\MessageController as PhotographerMessageController;
use App\Http\Controllers\Photographer\ReportSettingsController;
use App\Http\Controllers\Public\CartController;
use App\Http\Controllers\Public\CheckoutController;
use App\Http\Controllers\Public\CollectionController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DataRequestController;
use App\Http\Controllers\Public\DownloadController;
use App\Http\Controllers\Public\EventController as PublicEventController;
use App\Http\Controllers\Public\EventSubscriptionController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\MediaController as PublicMediaController;
use App\Http\Controllers\Public\MediaShareController;
use App\Http\Controllers\Public\MyPurchasesController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\PhotographerApplicationController;
use App\Http\Controllers\Public\PhotographerController as PublicPhotographerController;
use App\Http\Controllers\Public\PhotographerQuestionController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TeamProfileController;
use App\Models\Event;
use App\Models\HeroSlide;
use App\Models\Media;
use App\Services\MediaStorage;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'heroSlides' => HeroSlide::query()->activeOrdered()->get()->map(fn (HeroSlide $slide) => [
            'type' => $slide->type,
            'url' => Storage::disk(MediaStorage::public())->url($slide->isVideo() ? $slide->video_path : $slide->image_path),
            'poster' => $slide->poster_path ? Storage::disk(MediaStorage::public())->url($slide->poster_path) : null,
        ])->values(),
        'stats' => [
            'photos' => Media::where('type', Media::TYPE_PHOTO)->where('status', Media::STATUS_READY)->count(),
            'videos' => Media::where('type', Media::TYPE_VIDEO)->where('status', Media::STATUS_READY)->count(),
            'locations' => Event::where('status', Event::STATUS_LIVE)->distinct()->count('location'),
            'countries' => Event::where('status', Event::STATUS_LIVE)->whereNotNull('country_id')->distinct()->count('country_id'),
        ],
    ]);
})->name('home');

// Nyelvvaltas (HU/EN) — EPIC-14
Route::put('/locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// SEO: dinamikus sitemap + robots (a statikus public/robots.txt helyett)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// GEO: AI-crawlereknek szánt oldaltérkép (ld. App\Services\GeoSettings)
Route::get('/llms.txt', LlmsController::class)->name('llms');

// Fotos mobil PWA feltolto oldal (EPIC-15) — minden feltoltesre jogosult szerep
// (a sw.js + manifest.webmanifest a public/ gyokerbol statikusan szolgalodik ki,
//  a vite build masolja oda a public/build/-bol)
Route::middleware(['auth', 'role:superadmin|admin|photographer'])->group(function () {
    Route::get('/upload', [MobileUploadController::class, 'index'])->name('mobile-upload');

    // Saját nyilvános profil (a „Fotósok" oldalon megjelenő adatok) — a fotós maga szerkeszti
    Route::get('/profil', [TeamProfileController::class, 'edit'])->name('team.profile.edit');
    Route::post('/profil', [TeamProfileController::class, 'update'])->name('team.profile.update');
    Route::post('/profil/megallapodas', [TeamProfileController::class, 'acceptAgreement'])->name('team.profile.agreement');
});

// Nyilvanos galeria + kosar — EPIC-06
Route::get('/events', [PublicEventController::class, 'index'])->name('public.events.index');
Route::get('/events/{event:slug}', [PublicEventController::class, 'show'])->name('public.events.show');
Route::get('/media/{media}', [PublicMediaController::class, 'show'])->name('public.media.show');
Route::get('/cart', [CartController::class, 'index'])->middleware('payment.settings')->name('public.cart');

// Vasarlas + fizetes (Stripe / SimplePay) — EPIC-07. A payment.settings middleware a
// site_settings-bol a config-ba tolti a szolgaltato-kulcsokat (admin: /admin/settings/critical).
Route::middleware('payment.settings')->group(function () {
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('public.checkout.store');
    Route::get('/checkout/success', [CheckoutController::class, 'success'])->name('public.checkout.success');
    Route::get('/checkout/simplepay/return', [CheckoutController::class, 'simplePayReturn'])->name('public.checkout.simplepay.return');
    Route::get('/checkout/barion/return', [CheckoutController::class, 'barionReturn'])->name('public.checkout.barion.return');
});
Route::get('/checkout/cancel', [CheckoutController::class, 'cancel'])->name('public.checkout.cancel');

Route::get('/download/{token}', [DownloadController::class, 'show'])->name('public.download.show');
Route::get('/download/{token}/media/{media}/{format}', [DownloadController::class, 'file'])->name('public.download.file');
Route::get('/download/{token}/zip', [DownloadController::class, 'zip'])->name('public.download.zip');
Route::get('/download/{token}/invoice', [DownloadController::class, 'invoice'])->name('public.download.invoice');

// Kollekcio / wishlist — EPIC-17
Route::get('/collection', [CollectionController::class, 'index'])->name('public.collection');
Route::get('/collection/share/{token}', [CollectionController::class, 'shared'])->name('public.collection.shared');

// Media megosztas (vizjeles publikus link) — EPIC-17
Route::post('/media/{media}/share', [MediaShareController::class, 'store'])->middleware('throttle:20,1')->name('public.media.share');
Route::get('/share/{token}', [MediaShareController::class, 'show'])->name('public.share');

// Esemeny-ertesito feliratkozas — EPIC-17
Route::get('/unsubscribe/{token}', [EventSubscriptionController::class, 'destroy'])->name('public.unsubscribe');

// Korabbi vasarlasaim (OTP) — EPIC-17
Route::get('/my-purchases', [MyPurchasesController::class, 'show'])->name('public.my-purchases');
Route::post('/my-purchases/request-otp', [MyPurchasesController::class, 'requestOtp'])->middleware('throttle:6,1')->name('public.my-purchases.request-otp');
Route::post('/my-purchases/verify', [MyPurchasesController::class, 'verify'])->middleware('throttle:10,1')->name('public.my-purchases.verify');
Route::post('/my-purchases/resend', [MyPurchasesController::class, 'resend'])->name('public.my-purchases.resend');
Route::post('/my-purchases/logout', [MyPurchasesController::class, 'logout'])->name('public.my-purchases.logout');

// Informacios aloldalak — EPIC-11
Route::get('/about', [PageController::class, 'about'])->name('public.about');
Route::get('/social', [PageController::class, 'community'])->name('public.community');
Route::get('/photographers', [PublicPhotographerController::class, 'index'])->name('public.photographers');
Route::get('/csatlakozz', [PhotographerApplicationController::class, 'create'])->name('public.photographers.apply');
Route::post('/csatlakozz', [PhotographerApplicationController::class, 'store'])->middleware('throttle:contact')->name('public.photographers.apply.store');
Route::get('/privacy', [PageController::class, 'privacy'])->name('public.privacy');
Route::get('/shop', [PageController::class, 'shop'])->name('public.shop');
Route::get('/impresszum', [PageController::class, 'impressum'])->name('public.impressum');
Route::get('/aszf', [PageController::class, 'terms'])->name('public.terms');
Route::get('/faq', [FaqController::class, 'index'])->name('public.faq');
Route::get('/contact', [ContactController::class, 'create'])->name('public.contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('public.contact.store');
Route::post('/media/{media}/question', [PhotographerQuestionController::class, 'store'])->middleware('throttle:contact')->name('public.media.question');

// GDPR adatkiadási / törlési kérelem
Route::get('/adatvedelem/kerelem', [DataRequestController::class, 'create'])->name('public.data-request');
Route::post('/adatvedelem/kerelem', [DataRequestController::class, 'store'])->middleware('throttle:3,10')->name('public.data-request.store');
Route::get('/adatvedelem/kerelem/{token}', [DataRequestController::class, 'verify'])->name('public.data-request.verify');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);

    // Kétfaktoros kihívás — a jelszó után, a bejelentkezés befejezése előtt
    Route::get('/two-factor-challenge', [TwoFactorChallengeController::class, 'create'])->name('two-factor.challenge');
    Route::post('/two-factor-challenge', [TwoFactorChallengeController::class, 'store'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

// Fotos/admin meghivo elfogadasa — EPIC-09
Route::middleware('guest')->group(function () {
    Route::get('/invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
    Route::post('/invitations/{token}', [InvitationController::class, 'store'])->name('invitations.store');
});

// Admin / superadmin: sajat dashboard — EPIC-03, teljes KPI+diagram verzio EPIC-08
Route::middleware(['auth', 'role:superadmin|admin', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
    Route::post('/orders/{order}/resend-email', [DashboardController::class, 'resendOrderEmail'])->name('orders.resend-email');
    Route::post('/dashboard/alerts/dismiss', [DashboardController::class, 'dismissAlert'])->name('dashboard.alerts.dismiss');
    Route::post('/dashboard/onboarding/dismiss', [DashboardController::class, 'dismissOnboarding'])->name('dashboard.onboarding.dismiss');
    Route::post('/dashboard/media/{media}/reprocess', [DashboardController::class, 'reprocessMedia'])->name('dashboard.media.reprocess');
    Route::get('/dashboard/photographers/export', [DashboardController::class, 'exportPhotographerComparison'])->name('dashboard.photographers.export');
    Route::get('/dashboard/stats/export', [DashboardController::class, 'exportPeriodicStats'])->name('dashboard.stats.export');

    Route::get('/stats', [StatsController::class, 'index'])->name('stats');
    Route::get('/stats/export', [StatsController::class, 'export'])->name('stats.export');

    Route::get('/activity', [ActivityLogController::class, 'index'])->name('activity');

    // Kétfaktoros hitelesítés — minden admin a saját fiókjához
    Route::get('/settings/security', [SecuritySettingsController::class, 'index'])->name('settings.security');
    Route::post('/settings/security/2fa', [SecuritySettingsController::class, 'enable'])->name('settings.security.enable');
    Route::post('/settings/security/2fa/confirm', [SecuritySettingsController::class, 'confirm'])->name('settings.security.confirm');
    Route::post('/settings/security/2fa/recovery-codes', [SecuritySettingsController::class, 'regenerateRecoveryCodes'])->name('settings.security.recovery-codes');
    Route::delete('/settings/security/2fa', [SecuritySettingsController::class, 'disable'])->name('settings.security.disable');
    Route::put('/settings/security/policy', [SecuritySettingsController::class, 'updatePolicy'])->name('settings.security.policy');

    // Rendeléskezelő + visszatérítés
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/refund', [OrderController::class, 'refund'])->middleware('payment.settings')->name('orders.refund');
    Route::post('/orders/{order}/resend', [OrderController::class, 'resendEmail'])->name('orders.resend');
    Route::post('/orders/{order}/invoice', [OrderController::class, 'issueInvoice'])->name('orders.invoice');
    Route::post('/invoices/{invoice}/storno', [OrderController::class, 'stornoInvoice'])->name('invoices.storno');
    Route::get('/invoices/{invoice}/pdf', [OrderController::class, 'invoicePdf'])->name('invoices.pdf');

    // Üzenetkezelő — minden kapcsolati szál (support + fotósnak címzett)
    Route::get('/messages', [AdminMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{message}/reply', [AdminMessageController::class, 'reply'])->name('messages.reply');
    Route::put('/messages/{message}/status', [AdminMessageController::class, 'updateStatus'])->name('messages.status');
});

// Superadmin: NAS/tarhely beallitasok
Route::middleware(['auth', 'role:superadmin', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/data-requests', [AdminDataRequestController::class, 'index'])->name('data-requests.index');
    Route::get('/data-requests/{dataRequest}/download', [AdminDataRequestController::class, 'download'])->name('data-requests.download');
    Route::post('/data-requests/{dataRequest}/complete', [AdminDataRequestController::class, 'complete'])->name('data-requests.complete');
    Route::post('/data-requests/{dataRequest}/reject', [AdminDataRequestController::class, 'reject'])->name('data-requests.reject');

    Route::get('/settings/critical', [CriticalSettingsController::class, 'index'])->middleware('payment.settings')->name('settings.critical');
    Route::put('/settings/critical/payments', [CriticalSettingsController::class, 'updatePayments'])->name('settings.critical.payments');
    Route::put('/settings/critical/invoicing', [CriticalSettingsController::class, 'updateInvoicing'])->name('settings.critical.invoicing');
    Route::put('/settings/critical/identity', [CriticalSettingsController::class, 'updateIdentity'])->name('settings.critical.identity');
    Route::get('/settings/critical/identity/preview', [CriticalSettingsController::class, 'previewIdentity'])->name('settings.critical.identity.preview');
    Route::post('/settings/critical/identity/apply', [CriticalSettingsController::class, 'applyIdentity'])->name('settings.critical.identity.apply');

    Route::get('/settings/storage', [StorageSettingsController::class, 'index'])->name('settings.storage');
    Route::put('/settings/storage', [StorageSettingsController::class, 'update'])->name('settings.storage.update');
    Route::post('/settings/storage/test', [StorageSettingsController::class, 'test'])->name('settings.storage.test');
    Route::put('/settings/storage/r2', [StorageSettingsController::class, 'updateR2'])->name('settings.storage.r2');
    Route::post('/settings/storage/r2/test', [StorageSettingsController::class, 'testR2'])->name('settings.storage.r2.test');
    Route::post('/media/{media}/retry-archive', [StorageSettingsController::class, 'retry'])->name('media.retry-archive');

    Route::get('/settings/watermark', [WatermarkSettingsController::class, 'index'])->name('settings.watermark');
    Route::put('/settings/watermark', [WatermarkSettingsController::class, 'update'])->name('settings.watermark.update');
    Route::post('/settings/watermark/preview', [WatermarkSettingsController::class, 'preview'])->name('settings.watermark.preview');

    Route::get('/settings/theme', [ThemeSettingsController::class, 'index'])->name('settings.theme');
    Route::put('/settings/theme', [ThemeSettingsController::class, 'update'])->name('settings.theme.update');

    Route::get('/settings/branding', [BrandingSettingsController::class, 'index'])->name('settings.branding');
    Route::put('/settings/branding', [BrandingSettingsController::class, 'update'])->name('settings.branding.update');

    Route::get('/settings/hero', [HeroSlideController::class, 'index'])->name('settings.hero');
    Route::post('/settings/hero', [HeroSlideController::class, 'store'])->name('settings.hero.store');
    Route::put('/settings/hero/reorder', [HeroSlideController::class, 'reorder'])->name('settings.hero.reorder');
    Route::put('/settings/hero/{heroSlide}', [HeroSlideController::class, 'update'])->name('settings.hero.update');
    Route::delete('/settings/hero/{heroSlide}', [HeroSlideController::class, 'destroy'])->name('settings.hero.destroy');

    Route::get('/settings/plate-recognition', [PlateRecognitionSettingsController::class, 'index'])->name('settings.plate-recognition');
    Route::put('/settings/plate-recognition', [PlateRecognitionSettingsController::class, 'update'])->name('settings.plate-recognition.update');

    Route::get('/settings/seo', [SeoSettingsController::class, 'index'])->name('settings.seo');
    Route::post('/settings/seo', [SeoSettingsController::class, 'update'])->name('settings.seo.update');

    Route::get('/settings/geo', [GeoController::class, 'index'])->name('settings.geo');
    Route::put('/settings/geo', [GeoController::class, 'update'])->name('settings.geo.update');

    Route::get('/settings/location-search', [LocationSearchController::class, 'index'])->name('settings.location-search');
    Route::put('/settings/location-search', [LocationSearchController::class, 'update'])->name('settings.location-search.update');

    Route::get('/settings/maintenance', [MaintenanceModeController::class, 'index'])->name('settings.maintenance');
    Route::put('/settings/maintenance', [MaintenanceModeController::class, 'update'])->name('settings.maintenance.update');

    Route::get('/settings/images', [PageImageLibraryController::class, 'index'])->name('settings.images');
    Route::post('/settings/images/convert', [PageImageLibraryController::class, 'convert'])->middleware('throttle:10,1')->name('settings.images.convert');
    Route::post('/settings/images/delete', [PageImageLibraryController::class, 'destroy'])->name('settings.images.delete');

    Route::get('/settings/legal', [LegalSettingsController::class, 'index'])->name('settings.legal');
    Route::put('/settings/legal', [LegalSettingsController::class, 'update'])->name('settings.legal.update');

    Route::get('/settings/social', [SocialSettingsController::class, 'index'])->name('settings.social');
    Route::put('/settings/social', [SocialSettingsController::class, 'update'])->name('settings.social.update');

    Route::get('/settings/pricing', [PricingSettingsController::class, 'index'])->name('settings.pricing');
    Route::put('/settings/pricing', [PricingSettingsController::class, 'update'])->name('settings.pricing.update');

    // Éles ↔ helyi adat-szinkron (forrás-kulcs + a helyi gépen: letöltés/visszaállítás gombok)
    Route::get('/settings/data-sync', [DataSyncController::class, 'index'])->name('settings.data-sync');
    Route::put('/settings/data-sync/source', [DataSyncController::class, 'updateSource'])->name('settings.data-sync.source');
    Route::put('/settings/data-sync/remote', [DataSyncController::class, 'saveRemote'])->name('settings.data-sync.remote');
    Route::delete('/settings/data-sync/remote', [DataSyncController::class, 'forgetRemote'])->name('settings.data-sync.remote.forget');
    Route::post('/settings/data-sync/test', [DataSyncController::class, 'testRemote'])->middleware('throttle:20,1')->name('settings.data-sync.test');
    Route::post('/settings/data-sync/pull-database', [DataSyncController::class, 'pullDatabase'])->middleware('throttle:6,1')->name('settings.data-sync.pull-database');
    Route::post('/settings/data-sync/pull-media', [DataSyncController::class, 'pullMedia'])->middleware('throttle:30,1')->name('settings.data-sync.pull-media');

    // Hibanapló + monitoring/mentés
    Route::get('/mail-log', [MailLogController::class, 'index'])->name('mail-log');

    Route::get('/errors', [ErrorEventController::class, 'index'])->name('errors.index');
    Route::post('/errors/resolve-all', [ErrorEventController::class, 'resolveAll'])->name('errors.resolve-all');
    Route::post('/errors/{errorEvent}/resolve', [ErrorEventController::class, 'resolve'])->name('errors.resolve');
    Route::post('/errors/{errorEvent}/reopen', [ErrorEventController::class, 'reopen'])->name('errors.reopen');
    Route::delete('/errors/{errorEvent}', [ErrorEventController::class, 'destroy'])->name('errors.destroy');

    Route::put('/settings/critical/mail', [CriticalSettingsController::class, 'updateMail'])->name('settings.critical.mail');
    Route::put('/settings/critical/captcha', [CriticalSettingsController::class, 'updateCaptcha'])->name('settings.critical.captcha');
    Route::post('/settings/critical/mail/test', [CriticalSettingsController::class, 'sendTestMail'])->middleware('throttle:6,1')->name('settings.critical.mail.test');
    Route::put('/settings/critical/monitoring', [CriticalSettingsController::class, 'updateMonitoring'])->name('settings.critical.monitoring');
    Route::put('/settings/critical/scheduler', [CriticalSettingsController::class, 'updateScheduler'])->name('settings.critical.scheduler');
    Route::post('/settings/critical/scheduler/test', [CriticalSettingsController::class, 'testScheduler'])->middleware('throttle:10,1')->name('settings.critical.scheduler.test');
    Route::post('/settings/critical/backup', [CriticalSettingsController::class, 'runBackup'])->name('settings.critical.backup');
    Route::get('/settings/critical/backup/{name}', [CriticalSettingsController::class, 'downloadBackup'])->name('settings.critical.backup.download');

    Route::get('/settings/mail', [MailTemplateController::class, 'index'])->name('settings.mail');
    Route::put('/settings/mail/{key}', [MailTemplateController::class, 'update'])->name('settings.mail.update');
    Route::post('/settings/mail/{key}/reset', [MailTemplateController::class, 'reset'])->name('settings.mail.reset');

    // Kép-visszakövetés (forensic vízjel)
    Route::get('/forensics', [ForensicController::class, 'index'])->name('forensics.index');
    Route::post('/forensics/identify', [ForensicController::class, 'identify'])->name('forensics.identify');
    Route::put('/forensics/toggle', [ForensicController::class, 'toggle'])->name('forensics.toggle');

    // Esemény-szervezők kifizetései
    Route::get('/organizer-payouts', [OrganizerPayoutController::class, 'index'])->name('organizer-payouts.index');
    Route::post('/organizer-payouts', [OrganizerPayoutController::class, 'store'])->name('organizer-payouts.store');
    Route::delete('/organizer-payouts/{organizerPayout}', [OrganizerPayoutController::class, 'destroy'])->name('organizer-payouts.destroy');

    Route::get('/photographers', [PhotographerController::class, 'index'])->name('photographers.index');
    Route::put('/photographers/settings', [PhotographerController::class, 'updateSettings'])->name('photographers.settings');
    Route::post('/photographers/invite', [PhotographerController::class, 'storeInvitation'])->name('photographers.invite');
    Route::post('/photographers/invitations/{invitation}/resend', [PhotographerController::class, 'resendInvitation'])->name('photographers.invitations.resend');
    Route::delete('/photographers/invitations/{invitation}', [PhotographerController::class, 'destroyInvitation'])->name('photographers.invitations.destroy');
    Route::get('/photographers/{user}', [PhotographerController::class, 'show'])->name('photographers.show');
    Route::put('/photographers/{user}', [PhotographerController::class, 'update'])->name('photographers.update');
    Route::post('/photographers/{user}/reset-password', [PhotographerController::class, 'resetPassword'])->name('photographers.reset-password');
    Route::delete('/photographers/{user}', [PhotographerController::class, 'destroy'])->name('photographers.destroy');

    // Fotós kifizetés-elszámolás — a felület a fotós-részletnézetben
    Route::get('/photographers/{user}/payout/export', [PayoutController::class, 'export'])->name('photographers.payout.export');
    Route::post('/photographers/{user}/payout', [PayoutController::class, 'store'])->name('photographers.payout.store');
    Route::post('/payouts/{payout}/paid', [PayoutController::class, 'markPaid'])->name('payouts.paid');
    Route::delete('/payouts/{payout}', [PayoutController::class, 'destroy'])->name('payouts.destroy');
});

// Admin + photographer kozos utvonalak: esemeny CRUD + media feltoltes/kezeles.
// A fotos is letrehozhat/szerkeszthet esemenyt, de csak a sajatjat — lasd EventController.
Route::middleware(['auth', 'role:superadmin|admin|photographer', '2fa'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/guide', [GuideController::class, 'index'])->name('guide');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('events.show');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::put('/events/{event}/cover', [EventController::class, 'setCover'])->name('events.cover');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{event}/media', [MediaController::class, 'store'])->name('events.media.store');
    Route::post('/events/{event}/media/bulk-delete', [MediaController::class, 'bulkDestroy'])->name('events.media.bulk-delete');
    Route::get('/media-import/browse', [MediaImportController::class, 'browse'])->name('media-import.browse');
    Route::post('/events/{event}/import', [MediaImportController::class, 'store'])->name('events.import.store');
    Route::get('/events/{event}/import/status', [MediaImportController::class, 'status'])->name('events.import.status');
    Route::post('/events/{event}/upload/sign', [MediaUploadController::class, 'sign'])->name('events.upload.sign');
    Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');
});

// Fotos sajat fooldala
Route::middleware(['auth', 'role:photographer'])->prefix('photographer')->name('photographer.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'photographer'])->name('dashboard');
    Route::put('/settings/reports', [ReportSettingsController::class, 'update'])->name('settings.reports');

    // Saját beérkezett üzenetek (a média-oldali „Kérdés a fotóshoz" űrlapról)
    Route::get('/messages', [PhotographerMessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{message}/reply', [PhotographerMessageController::class, 'reply'])->name('messages.reply');
});

// Esemeny-szervezo portal (read-only riportok a sajat esemenyeirol + reszesedes)
Route::middleware(['auth', 'role:organizer'])->prefix('organizer')->name('organizer.')->group(function () {
    Route::get('/dashboard', [OrganizerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/export', [OrganizerDashboardController::class, 'export'])->name('dashboard.export');
    Route::get('/events/{event}', [OrganizerDashboardController::class, 'event'])->name('events.show');
});

// 404 — nem letezo URL-ek. A fallback route a teljes `web` middleware-t megkapja
// (SetLocale + HandleInertiaRequests), igy a hibaoldal is forditott + markazott.
// A matched route-okbol dobott 404/403/419/... a bootstrap/app.php respond()-jaban.
Route::fallback(function () {
    return Inertia::render('Error', ['status' => 404])
        ->toResponse(request())
        ->setStatusCode(404);
});
