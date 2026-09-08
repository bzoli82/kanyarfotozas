<?php

namespace App\Services\Invoicing;

use App\Models\Invoice;
use App\Models\Order;
use App\Services\InvoiceSettings;
use App\Services\SiteBranding;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Billingo v3 (https://api.billingo.hu/v3) — a számlát kiállítja, elektronikus
 * számlaként a NAV Online Számlának is jelenti (a Billingo oldalon konfigurálva).
 *
 * Folyamat: partner létrehozása -> dokumentum (számla) létrehozása -> PDF letöltés.
 * A számlán EGY tétel szerepel (a rendelés végösszege), így a kedvezmény/kupon
 * miatt sosincs kerekítési eltérés.
 */
class BillingoInvoiceProvider implements InvoiceProvider
{
    private const BASE = 'https://api.billingo.hu/v3';

    public function __construct(private InvoiceSettings $settings) {}

    public function provider(): string
    {
        return InvoiceSettings::PROVIDER_BILLINGO;
    }

    public function isConfigured(): bool
    {
        return $this->settings->provider() === InvoiceSettings::PROVIDER_BILLINGO
            && $this->settings->hasApiKey()
            && $this->settings->blockId() !== null;
    }

    public function issue(Order $order): IssuedInvoice
    {
        if (! $this->isConfigured()) {
            throw new InvoiceException('A Billingo nincs beállítva (API kulcs / számlatömb hiányzik).');
        }

        $partnerId = $this->createPartner($order);

        $doc = $this->post('/documents', [
            'partner_id' => $partnerId,
            'block_id' => $this->settings->blockId(),
            'type' => 'invoice',
            'fulfillment_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'payment_method' => 'bankcard',
            'language' => 'hu',
            'currency' => 'HUF',
            'conversion_rate' => 1,
            'electronic' => true,
            'paid' => true,
            'items' => [[
                'name' => $this->lineName($order),
                'unit_price' => (int) $order->total_cents,
                'unit_price_type' => 'gross',
                'quantity' => 1,
                'unit' => 'db',
                'vat' => $this->vatFor($order),
                'comment' => 'Rendelés: '.($order->order_number ?? $order->id),
            ]],
        ]);

        return new IssuedInvoice(
            externalId: (string) $doc['id'],
            number: $doc['invoice_number'] ?? null,
            grossCents: (int) $order->total_cents,
            pdf: $this->downloadPdf((string) $doc['id']),
        );
    }

    public function storno(Invoice $invoice): IssuedInvoice
    {
        if (! $this->isConfigured()) {
            throw new InvoiceException('A Billingo nincs beállítva.');
        }

        if (blank($invoice->external_id)) {
            throw new InvoiceException('A számlához nincs Billingo azonosító — nem sztornózható.');
        }

        $doc = $this->post("/documents/{$invoice->external_id}/cancel", []);

        return new IssuedInvoice(
            externalId: (string) ($doc['id'] ?? $invoice->external_id),
            number: $doc['invoice_number'] ?? null,
            grossCents: -1 * (int) $invoice->gross_cents,
            pdf: isset($doc['id']) ? $this->downloadPdf((string) $doc['id']) : null,
        );
    }

    private function createPartner(Order $order): int
    {
        $isCompany = filled($order->billing_tax_number);

        $partner = $this->post('/partners', array_filter([
            'name' => $order->billing_name ?: 'Vásárló',
            'address' => array_filter([
                'country_code' => mb_strtoupper($order->billing_country ?: 'HU'),
                'post_code' => $order->billing_zip,
                'city' => $order->billing_city,
                'address' => $order->billing_address,
            ]),
            'emails' => [$order->buyer_email],
            'taxcode' => $isCompany ? $order->billing_tax_number : null,
        ]));

        if (blank($partner['id'] ?? null)) {
            throw new InvoiceException('A Billingo nem adott vissza partner-azonosítót.');
        }

        return (int) $partner['id'];
    }

    private function downloadPdf(string $documentId): ?string
    {
        $response = $this->client()->get(self::BASE."/documents/{$documentId}/download");

        return $response->successful() ? $response->body() : null;
    }

    private function lineName(Order $order): string
    {
        $count = $order->media()->count();

        return app(SiteBranding::class)->name().' — digitális felvétel(ek), '.$count.' db';
    }

    private function vatFor(Order $order): string
    {
        $country = mb_strtoupper($order->billing_country ?: 'HU');
        $euCountries = ['AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL', 'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE'];

        // EU-n kívüli magánszemély vevő: az ügylet az ÁFA területi hatályán kívül esik.
        if (! in_array($country, $euCountries, true)) {
            return 'EU-N KÍVÜLI';
        }

        return $this->settings->vatCode();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function post(string $path, array $body): array
    {
        $response = $this->client()->post(self::BASE.$path, $body);

        if (! $response->successful()) {
            throw new InvoiceException('Billingo hiba ('.$response->status().'): '.mb_substr($response->body(), 0, 300));
        }

        return (array) $response->json();
    }

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'X-API-KEY' => (string) $this->settings->apiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(30);
    }
}
