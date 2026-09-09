<?php

namespace App\Services;

use App\Models\SiteSetting;

/**
 * Két superadmin-kapcsoló, ami azt szabályozza, mennyit lát a NYILVÁNOS látogató
 * a fotósokból (anti-disintermediation: hogy a vásárló ne tudja megkerülni az
 * oldalt a fotós közvetlen megkeresésével). A kapcsolók a `/admin/photographers`
 * oldal tetején állíthatók.
 */
class PhotographerVisibility
{
    /**
     * A fotósok elérhetőségei (céges e-mail / weboldal / közösségi linkek)
     * megjelennek-e a „Fotósok" oldalon. Alap: KI.
     */
    public function contactsPublic(): bool
    {
        return (bool) SiteSetting::get('photographer_contacts_public', false);
    }

    public function setContactsPublic(bool $enabled): void
    {
        SiteSetting::set('photographer_contacts_public', $enabled ? '1' : '0');
    }

    /**
     * A KÉP-szintű fotós-attribúció („Fotós: X" a lightboxban / média-oldalon)
     * + az esemény-kereső „Fotós" szűrője. Alap: BE (a jelenlegi viselkedés).
     */
    public function attributionPublic(): bool
    {
        return (bool) SiteSetting::get('photographer_attribution_public', true);
    }

    public function setAttributionPublic(bool $enabled): void
    {
        SiteSetting::set('photographer_attribution_public', $enabled ? '1' : '0');
    }
}
