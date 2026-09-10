<?php

namespace App\Services;

use App\Models\Event;
use App\Models\HeroSlide;
use App\Models\Invitation;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\Payments\PaymentGatewayManager;

/**
 * „Első lépések" — az élesítés előtti / utáni beállítási teendők a valós
 * állapotból számolva. A dashboard-kártya ezt mutatja a superadminnak, amíg
 * nincs kész (vagy amíg el nem rejti). Feladat-orientált — a technikai
 * részletekért lásd `SystemReadiness` (a Kritikus beállítások zöld/sárga/piros).
 */
class OnboardingChecklist
{
    private const DISMISS_KEY = 'onboarding_dismissed';

    public function __construct(
        private PaymentGatewayManager $payments,
        private MailSettings $mail,
        private LegalPages $legal,
    ) {}

    /**
     * @return list<array{key: string, label: string, done: bool, href: string, hint: string}>
     */
    public function items(?User $user = null): array
    {
        $items = [
            [
                'key' => 'payment',
                'label' => 'Fizetési átjáró beállítva',
                'done' => count($this->payments->available()) > 0,
                'href' => '/admin/settings/critical',
                'hint' => 'Legalább egy szolgáltató (Stripe / SimplePay / Barion) kulcsokkal + engedélyezve.',
            ],
            [
                'key' => 'mail',
                'label' => 'Valódi e-mail küldés (nem napló)',
                'done' => $this->mail->isConfigured(),
                'href' => '/admin/settings/critical#mail',
                'hint' => 'SMTP beállítás — enélkül a visszaigazoló / letöltő e-mailek nem mennek ki.',
            ],
            [
                'key' => 'legal',
                'label' => 'Impresszum + ÁSZF kitöltve',
                'done' => ! blank(SiteSetting::get('legal_impressum')) && ! blank(SiteSetting::get('legal_terms')),
                'href' => '/admin/settings/legal',
                'hint' => 'A publikus /impresszum és /aszf tartalma — magyar webshopnál kötelező.',
            ],
            [
                'key' => 'hero',
                'label' => 'Főoldali hero kép feltöltve',
                'done' => HeroSlide::query()->exists(),
                'href' => '/admin/settings/hero',
                'hint' => 'Kép nélkül a főoldali diavetítés a beépített sötét gradienst mutatja.',
            ],
            [
                'key' => 'photographer',
                'label' => 'Legalább egy fotós (vagy meghívó)',
                'done' => User::query()->where('role', User::ROLE_PHOTOGRAPHER)->exists()
                    || Invitation::query()->whereNull('accepted_at')->exists(),
                'href' => '/admin/photographers',
                'hint' => 'A tartalom a fotósoktól jön — hívj meg legalább egyet.',
            ],
            [
                'key' => 'event',
                'label' => 'Első esemény létrehozva',
                'done' => Event::query()->exists(),
                'href' => '/admin/events/create',
                'hint' => 'Egy fotózás (helyszín, dátum, árazás), majd a képek feltöltése.',
            ],
        ];

        if ($user) {
            $items[] = [
                'key' => '2fa',
                'label' => 'Kétfaktoros hitelesítés a fiókodon',
                'done' => $user->two_factor_confirmed_at !== null,
                'href' => '/admin/settings/security',
                'hint' => 'A superadmin fiók a legérzékenyebb — kapcsold be a 2FA-t.',
            ];
        }

        return $items;
    }

    public function dismissed(): bool
    {
        return (bool) SiteSetting::get(self::DISMISS_KEY, false);
    }

    public function dismiss(bool $value = true): void
    {
        SiteSetting::set(self::DISMISS_KEY, $value ? '1' : '0');
    }

    /**
     * A dashboard-nak: a lista + a haladás, VAGY null, ha kész / elrejtve.
     *
     * @return array{items: list<array{key: string, label: string, done: bool, href: string, hint: string}>, done: int, total: int}|null
     */
    public function forDashboard(User $user): ?array
    {
        if ($this->dismissed()) {
            return null;
        }

        $items = $this->items($user);
        $done = count(array_filter($items, fn ($i) => $i['done']));

        if ($done === count($items)) {
            return null;
        }

        return ['items' => $items, 'done' => $done, 'total' => count($items)];
    }
}
