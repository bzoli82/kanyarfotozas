<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * Az ugyfeleknek/fotosoknak kikuldott e-mailek testreszabhato szoveges blokkjai
 * (targy, cimsor, bevezeto, zaro bekezdes, alairas) — a /admin/settings/mail
 * feluleten superadmin szerkeszti, a mailable-ok es a blade nezetek innen kerik le
 * az ertekeket, alapertelmezett ertekre visszaesve, ha nincs feluliras.
 *
 * A dinamikus tartalom (letoltes-gombok, tetellista, riport-tablazat) tovabbra is
 * a blade nezetben van kodolva — csak a korulotte levo proza szerkesztheto.
 */
class MailTemplates
{
    /** SiteSetting kulcs-prefix a felulirasokhoz (JSON-kodolt reszmezokkel). */
    private const PREFIX = 'mail_tpl:';

    /** A szerkesztheto szoveges mezok, minden sablonnal azonos szerkezet. */
    public const FIELDS = ['subject', 'heading', 'intro', 'outro', 'signature'];

    /**
     * @return array<string, array{
     *     label: string,
     *     description: string,
     *     placeholders: array<string, string>,
     *     defaults: array<string, string>
     * }>
     */
    public static function registry(): array
    {
        $signature = 'Üdvözlettel,\n:app_name';

        return [
            'order_confirmation' => [
                'label' => 'Vásárlás visszaigazolása',
                'description' => 'A sikeres fizetés után az ügyfélnek kiküldött e-mail a letöltési linkekkel.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'order_number' => 'A rendelés sorszáma',
                    'order_id' => 'A rendelés belső azonosítója',
                    'expires_at' => 'A letöltési link lejárati ideje',
                    'item_count' => 'A megvásárolt tételek száma',
                ],
                'defaults' => [
                    'subject' => ':app_name — vásárlás visszaigazolása (:order_number)',
                    'heading' => 'Köszönjük a vásárlást!',
                    'intro' => 'A rendelésed sikeresen fizetve lett. A megvásárolt tartalmat az alábbi közvetlen linkekről, vagy a letöltési oldalról töltheted le — a képeknél mindkét formátumot (JPEG és WebP) megkapod.',
                    'outro' => 'Ha bármelyik gomb nem működik, másold be a letöltési linket a böngésződbe.',
                    'signature' => $signature,
                ],
            ],
            'order_refunded' => [
                'label' => 'Visszatérítés visszaigazolása',
                'description' => 'Az ügyfélnek kiküldött e-mail, ha egy rendelést (részben vagy teljesen) visszatérítettek.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'order_number' => 'A rendelés sorszáma',
                    'amount' => 'A visszatérített összeg',
                ],
                'defaults' => [
                    'subject' => ':app_name — visszatérítés (:order_number)',
                    'heading' => 'Visszatérítettük a rendelésed',
                    'intro' => 'A(z) :order_number rendeléshez :amount összegű visszatérítést indítottunk el. A jóváírás a fizetési szolgáltatótól függően néhány munkanapot vehet igénybe.',
                    'outro' => 'Ha a teljes összeget visszatérítettük, a letöltési link már nem használható.',
                    'signature' => $signature,
                ],
            ],
            'download_reminder' => [
                'label' => 'Letöltési emlékeztető',
                'description' => 'Emlékeztető, ha a letöltési link 48 órán belül lejár és van még le nem töltött tétel.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'expires_at' => 'A letöltési link lejárati ideje',
                    'item_count' => 'A letöltésre váró tételek száma',
                ],
                'defaults' => [
                    'subject' => 'Emlékeztető — hamarosan lejár a letöltési linked',
                    'heading' => 'Ne felejtsd el letölteni!',
                    'intro' => 'A letöltési linked hamarosan lejár — :item_count tétel vár letöltésre a rendelésedből.',
                    'outro' => 'A lejárat után a link már nem használható. Ha technikai gondba ütközöl, keress minket a Kapcsolat oldalon.',
                    'signature' => $signature,
                ],
            ],
            'contact_confirmation' => [
                'label' => 'Kapcsolat — visszaigazolás a feladónak',
                'description' => 'A kapcsolati űrlap beküldése után a feladónak küldött automatikus visszaigazolás.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'name' => 'A feladó neve',
                    'subject' => 'Az üzenet tárgya',
                ],
                'defaults' => [
                    'subject' => 'Megkaptuk az üzeneted — :app_name',
                    'heading' => 'Köszönjük, hogy írtál!',
                    'intro' => 'Szia :name! Megkaptuk az üzeneted, és hamarosan válaszolunk rá.',
                    'outro' => '',
                    'signature' => $signature,
                ],
            ],
            'contact_notification' => [
                'label' => 'Kapcsolat — értesítés az adminoknak',
                'description' => 'Belső értesítő e-mail, amit minden superadmin megkap új kapcsolatfelvételkor.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'name' => 'A feladó neve',
                    'email' => 'A feladó e-mail címe',
                    'subject' => 'Az üzenet tárgya',
                ],
                'defaults' => [
                    'subject' => 'Új kapcsolatfelvétel — :name',
                    'heading' => 'Új kapcsolatfelvétel',
                    'intro' => '',
                    'outro' => '',
                    'signature' => '',
                ],
            ],
            'contact_reply' => [
                'label' => 'Kapcsolat — válasz a feladónak',
                'description' => 'Amikor egy admin vagy fotós válaszol egy beérkezett üzenetre, a feladó ezt az e-mailt kapja a válasz szövegével.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'name' => 'A feladó neve',
                    'subject' => 'Az eredeti üzenet tárgya',
                    'replier' => 'A válaszoló neve (fotós vagy csapat)',
                ],
                'defaults' => [
                    'subject' => 'Válasz: :subject',
                    'heading' => 'Válasz az üzenetedre',
                    'intro' => 'Szia :name! :replier válaszolt a(z) „:subject" tárgyú üzenetedre:',
                    'outro' => 'Ha további kérdésed van, egyszerűen válaszolj erre az e-mailre.',
                    'signature' => $signature,
                ],
            ],
            'photographer_invitation' => [
                'label' => 'Fotós meghívó',
                'description' => 'A fotósnak/adminnak kiküldött meghívó e-mail az elfogadó linkkel.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'role' => 'A meghívott szerepkör (fotós / adminisztrátor)',
                    'invited_by' => 'A meghívó neve',
                    'expires_at' => 'A meghívó lejárati ideje',
                ],
                'defaults' => [
                    'subject' => 'Meghívó a :app_name csapatába',
                    'heading' => 'Meghívó a :app_name csapatába',
                    'intro' => ':invited_by meghívott, hogy csatlakozz a :app_name platformhoz :role szerepkörben.',
                    'outro' => 'Ha a gomb nem működik, másold be az elfogadó linket a böngésződbe.',
                    'signature' => $signature,
                ],
            ],
            'temporary_password' => [
                'label' => 'Ideiglenes jelszó',
                'description' => 'Jelszó-visszaállításkor a felhasználónak küldött ideiglenes jelszó.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'name' => 'A felhasználó neve',
                ],
                'defaults' => [
                    'subject' => 'Ideiglenes jelszó — :app_name',
                    'heading' => 'Ideiglenes jelszó',
                    'intro' => 'Szia :name! A jelszavad visszaállítva. Az alábbi ideiglenes jelszóval tudsz bejelentkezni — javasoljuk, hogy bejelentkezés után azonnal változtasd meg.',
                    'outro' => '',
                    'signature' => $signature,
                ],
            ],
            'photographer_report' => [
                'label' => 'Fotós értékesítési riport',
                'description' => 'A heti/havi értékesítési összesítő a fotósoknak.',
                'placeholders' => [
                    'app_name' => 'Az oldal neve',
                    'name' => 'A fotós neve',
                    'period' => 'Az időszak megnevezése (heti / havi)',
                    'from' => 'Az időszak kezdete',
                    'to' => 'Az időszak vége',
                ],
                'defaults' => [
                    'subject' => ':period riport — :app_name',
                    'heading' => ':period riport',
                    'intro' => 'Szia :name! Az alábbiakban az időszak (:from – :to) értékesítési összesítője.',
                    'outro' => '',
                    'signature' => $signature,
                ],
            ],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::registry());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::registry());
    }

    /**
     * A sablon nyers (feldolgozatlan, placeholder-eket meg tartalmazo) mezoi:
     * az alapertelmezett ertekek az esetleges felulirasokkal osszefesulve.
     *
     * @return array<string, string>
     */
    public static function raw(string $key): array
    {
        $defaults = self::registry()[$key]['defaults'];
        $overrides = self::overrides($key);

        $merged = [];
        foreach (self::FIELDS as $field) {
            $value = $overrides[$field] ?? $defaults[$field] ?? '';
            $merged[$field] = str_replace('\n', "\n", $value);
        }

        return $merged;
    }

    /**
     * A sablon mezoi a megadott adatokkal behelyettesitve — ezt hasznaljak
     * a mailable-ok es a blade nezetek.
     *
     * @param  array<string, string|int|null>  $data
     * @return array<string, string>
     */
    public static function resolve(string $key, array $data = []): array
    {
        $data['app_name'] ??= app(SiteBranding::class)->name();

        return array_map(
            fn (string $text) => self::substitute($text, $data),
            self::raw($key)
        );
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function update(string $key, array $values): void
    {
        $clean = [];
        foreach (self::FIELDS as $field) {
            $clean[$field] = trim((string) ($values[$field] ?? ''));
        }

        SiteSetting::set(self::PREFIX.$key, json_encode($clean, JSON_UNESCAPED_UNICODE));
    }

    public static function reset(string $key): void
    {
        SiteSetting::set(self::PREFIX.$key, null);
    }

    /**
     * @return array<string, string>
     */
    private static function overrides(string $key): array
    {
        $stored = SiteSetting::get(self::PREFIX.$key);

        if (! is_string($stored) || $stored === '') {
            return [];
        }

        $decoded = json_decode($stored, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, string|int|null>  $data
     */
    private static function substitute(string $text, array $data): string
    {
        // Hosszabb tokenek elobb, hogy a :name ne rontsa el a :app_name-et.
        uksort($data, fn ($a, $b) => strlen((string) $b) <=> strlen((string) $a));

        foreach ($data as $token => $value) {
            $text = str_replace(':'.$token, (string) ($value ?? ''), $text);
        }

        return Str::of($text)->trim()->value();
    }
}
