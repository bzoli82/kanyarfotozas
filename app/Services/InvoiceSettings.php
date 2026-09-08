<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Crypt;

/**
 * Számlázás beállításai — a superadmin a /admin/settings/critical oldalon állítja
 * be. Az API kulcs Crypt-titkosítva a `site_settings`-ben (mint NasConnection /
 * PaymentSettings).
 *
 * Jelenleg egy szolgáltató támogatott: **Billingo v3**. Az `InvoiceProvider`
 * absztrakció miatt később a Számlázz.hu is beköthető.
 */
class InvoiceSettings
{
    public const PROVIDER_NONE = 'none';

    public const PROVIDER_BILLINGO = 'billingo';

    /** @var list<string> */
    public const PROVIDERS = [self::PROVIDER_NONE, self::PROVIDER_BILLINGO];

    /** Elfogadott ÁFA-jelölések (Billingo). `AAM` = alanyi adómentesség. */
    public const VAT_CODES = ['AAM', '27%', '5%', '18%', 'EU', 'EU-N KÍVÜLI'];

    public const DEFAULT_VAT = 'AAM';

    public function provider(): string
    {
        $p = (string) SiteSetting::get('invoice_provider', self::PROVIDER_NONE);

        return in_array($p, self::PROVIDERS, true) ? $p : self::PROVIDER_NONE;
    }

    public function apiKey(): ?string
    {
        $stored = SiteSetting::get('invoice_api_key');

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey() !== null;
    }

    /** Billingo számlatömb (block) azonosító. */
    public function blockId(): ?int
    {
        $v = SiteSetting::get('invoice_block_id');

        return filled($v) ? (int) $v : null;
    }

    public function vatCode(): string
    {
        $v = (string) SiteSetting::get('invoice_vat', self::DEFAULT_VAT);

        return in_array($v, self::VAT_CODES, true) ? $v : self::DEFAULT_VAT;
    }

    /** Automatikusan állítson-e ki számlát a sikeres fizetés után. */
    public function autoIssue(): bool
    {
        return (bool) SiteSetting::get('invoice_auto', false);
    }

    /** Kész-e a rendszer számlát kiállítani (szolgáltató + kulcs + billingo esetén block). */
    public function isConfigured(): bool
    {
        if ($this->provider() === self::PROVIDER_NONE || ! $this->hasApiKey()) {
            return false;
        }

        if ($this->provider() === self::PROVIDER_BILLINGO) {
            return $this->blockId() !== null;
        }

        return true;
    }

    /**
     * @return array<string, mixed> (soha nem a kulcs)
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider(),
            'has_api_key' => $this->hasApiKey(),
            'block_id' => $this->blockId(),
            'vat' => $this->vatCode(),
            'auto' => $this->autoIssue(),
            'configured' => $this->isConfigured(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(array $data): void
    {
        $provider = in_array($data['provider'] ?? null, self::PROVIDERS, true) ? $data['provider'] : self::PROVIDER_NONE;
        SiteSetting::set('invoice_provider', $provider);
        SiteSetting::set('invoice_block_id', filled($data['block_id'] ?? null) ? (string) (int) $data['block_id'] : '');
        SiteSetting::set('invoice_vat', in_array($data['vat'] ?? null, self::VAT_CODES, true) ? $data['vat'] : self::DEFAULT_VAT);
        SiteSetting::set('invoice_auto', ! empty($data['auto']) ? '1' : '');

        if (filled($data['api_key'] ?? null)) {
            SiteSetting::set('invoice_api_key', Crypt::encryptString((string) $data['api_key']));
        }

        if (! empty($data['clear_api_key'])) {
            SiteSetting::set('invoice_api_key', '');
        }
    }
}
