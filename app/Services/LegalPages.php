<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Str;

/**
 * Az Impresszum és az ÁSZF (Általános Szerződési Feltételek) tartalma —
 * a superadmin a /admin/settings/legal oldalon **Markdown**-ban szerkeszti,
 * a tartalom a `site_settings`-ben tárolódik. A publikus oldalak a rendered
 * HTML-t kapják (`Str::markdown`, nyers HTML kiszűrve).
 *
 * A cégadatok/jogi szöveg az üzemeltetőtől jönnek — a defaultok csak vázak
 * `[kitöltendő]` jelölőkkel, hogy ne legyen üres oldal.
 */
class LegalPages
{
    public function impressumMarkdown(): string
    {
        return (string) (SiteSetting::get('legal_impressum') ?? $this->defaultImpressum());
    }

    public function termsMarkdown(): string
    {
        return (string) (SiteSetting::get('legal_terms') ?? $this->defaultTerms());
    }

    public function impressumHtml(): string
    {
        return $this->render($this->impressumMarkdown());
    }

    public function termsHtml(): string
    {
        return $this->render($this->termsMarkdown());
    }

    public function termsUpdatedAt(): ?string
    {
        return SiteSetting::get('legal_terms_updated_at');
    }

    /**
     * @return array{impressum: string, terms: string, terms_updated_at: ?string, impressum_is_default: bool, terms_is_default: bool}
     */
    public function forForm(): array
    {
        return [
            // A textarea a vázzal töltődik elő, ha még nincs mentve semmi.
            'impressum' => $this->impressumMarkdown(),
            'terms' => $this->termsMarkdown(),
            'terms_updated_at' => $this->termsUpdatedAt(),
            'impressum_is_default' => blank(SiteSetting::get('legal_impressum')),
            'terms_is_default' => blank(SiteSetting::get('legal_terms')),
        ];
    }

    /**
     * @param  array{impressum?: string, terms?: string}  $data
     */
    public function update(array $data): void
    {
        $newTerms = (string) ($data['terms'] ?? '');

        if ($newTerms !== (string) (SiteSetting::get('legal_terms') ?? '')) {
            SiteSetting::set('legal_terms_updated_at', now()->toDateString());
        }

        SiteSetting::set('legal_impressum', (string) ($data['impressum'] ?? ''));
        SiteSetting::set('legal_terms', $newTerms);
    }

    private function render(string $markdown): string
    {
        if (blank(trim($markdown))) {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    private function defaultImpressum(): string
    {
        $brand = rescue(fn () => app(SiteBranding::class)->name(), 'KanyarFotózás', false);

        return <<<MD
        ## Impresszum

        **A szolgáltató neve:** [kitöltendő – cégnév / egyéni vállalkozó neve]

        **Székhely:** [kitöltendő – irányítószám, település, cím]

        **Adószám:** [kitöltendő]

        **Cégjegyzékszám / nyilvántartási szám:** [kitöltendő]

        **Képviselő:** [kitöltendő]

        **E-mail:** [kitöltendő]

        **Telefon:** [kitöltendő]

        **Tárhelyszolgáltató:** [kitöltendő – név, székhely, e-mail]

        A(z) {$brand} oldalon értékesített digitális tartalom (fotó, videó) letölthető formában kerül átadásra.
        MD;
    }

    private function defaultTerms(): string
    {
        $brand = rescue(fn () => app(SiteBranding::class)->name(), 'KanyarFotózás', false);

        return <<<MD
        ## Általános Szerződési Feltételek

        *Ez egy vázlat – az üzemeltetőnek (szükség szerint ügyvéddel) véglegesítenie kell.*

        ### 1. A szolgáltató
        A szolgáltató adatait az [Impresszum](/impresszum) tartalmazza.

        ### 2. A szolgáltatás
        A(z) {$brand} egy online piactér, ahol motorsport-eseményeken készült fényképek és videók
        vásárolhatók meg és tölthetők le digitálisan, regisztráció nélkül, e-mail-cím megadásával.

        ### 3. A megrendelés és a fizetés
        A vásárló a kosárba helyezett tételeket a pénztárban, bankkártyás fizetéssel vásárolja meg.
        A szerződés a fizetés visszaigazolásával jön létre. A vásárló a visszaigazolást és a letöltési
        linket e-mailben kapja meg.

        ### 4. Elállási jog
        A 45/2014. (II. 26.) Korm. rendelet 29. § (1) m) pontja alapján a nem tárgyi adathordozón
        nyújtott digitális tartalom esetén a fogyasztót **nem illeti meg az elállási jog**, ha a
        teljesítés a fogyasztó kifejezett, előzetes beleegyezésével megkezdődött, és a fogyasztó
        tudomásul vette, hogy a teljesítés megkezdésével elveszíti az elállási jogát. A vásárló ezt a
        nyilatkozatot a pénztárban, a vásárlás előtt teszi meg.

        ### 5. Panaszkezelés, jogorvoslat
        Panasz: az [Impresszumban](/impresszum) megadott e-mail-címen. Békéltető testület, illetve a
        fogyasztóvédelmi hatóság eljárása kezdeményezhető. Online vitarendezési platform:
        https://ec.europa.eu/consumers/odr

        ### 6. Adatkezelés
        Az adatkezelés részleteit az [Adatvédelmi tájékoztató](/privacy) tartalmazza.

        ### 7. Szellemi tulajdon
        A megvásárolt felvétel a vásárló személyes célú felhasználására szolgál. A felvételen szereplő
        szerzői jogok a fotóst / a szolgáltatót illetik; a megvásárlás nem ruházza át a szerzői jogokat.
        MD;
    }
}
