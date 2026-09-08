<?php

namespace Tests\Feature;

use App\Jobs\IssueInvoiceJob;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Order;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\CheckoutService;
use App\Services\Invoicing\InvoiceException;
use App\Services\Invoicing\InvoiceManager;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoicingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        Storage::fake('local');

        SiteSetting::set('invoice_provider', 'billingo');
        SiteSetting::set('invoice_api_key', Crypt::encryptString('billingo-key'));
        SiteSetting::set('invoice_block_id', '42');
        SiteSetting::set('invoice_vat', 'AAM');
        SiteSetting::set('invoice_auto', '1');
    }

    private function fakeBillingo(): void
    {
        Http::fake([
            'api.billingo.hu/v3/partners' => Http::response(['id' => 777], 201),
            'api.billingo.hu/v3/documents' => Http::response(['id' => 555, 'invoice_number' => '2026-0001'], 201),
            'api.billingo.hu/v3/documents/555/download' => Http::response('%PDF-1.4 fake', 200),
            'api.billingo.hu/v3/documents/555/cancel' => Http::response(['id' => 556, 'invoice_number' => '2026-0001-S'], 201),
            'api.billingo.hu/v3/documents/556/download' => Http::response('%PDF-1.4 storno', 200),
        ]);
    }

    private function paidOrder(): Order
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 3000]);
        $order = Order::factory()->paid()->create([
            'total_cents' => 3000,
            'billing_name' => 'Teszt Elek',
            'billing_country' => 'HU',
        ]);
        $order->media()->attach($media->id, ['price_cents' => 3000]);

        return $order->fresh();
    }

    public function test_checkout_requires_billing_data_when_invoicing_is_configured(): void
    {
        $media = Media::factory()->create(['status' => Media::STATUS_READY, 'price_cents' => 1000]);

        $this->postJson('/checkout', ['media_ids' => [$media->id], 'email' => 'a@b.hu', 'terms_accepted' => true])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['billing_name', 'billing_country']);
    }

    public function test_manager_issues_a_billingo_invoice_and_stores_the_pdf(): void
    {
        $this->fakeBillingo();
        $order = $this->paidOrder();

        $invoice = app(InvoiceManager::class)->issueFor($order);

        $this->assertTrue($invoice->isIssued());
        $this->assertSame('2026-0001', $invoice->number);
        $this->assertSame('555', $invoice->external_id);
        Storage::disk('local')->assertExists($invoice->pdf_path);

        // Idempotens: másodszor ugyanazt adja vissza, nincs új Billingo hívás a dokumentumra.
        $again = app(InvoiceManager::class)->issueFor($order);
        $this->assertSame($invoice->id, $again->id);
    }

    public function test_failed_issue_records_the_error(): void
    {
        Http::fake(['api.billingo.hu/*' => Http::response(['error' => 'unauthorized'], 401)]);
        $order = $this->paidOrder();

        try {
            app(InvoiceManager::class)->issueFor($order);
            $this->fail('expected exception');
        } catch (InvoiceException) {
        }

        $rec = $order->invoices()->first();
        $this->assertSame('failed', $rec->status);
        $this->assertNotNull($rec->error);
    }

    public function test_markpaid_dispatches_the_invoice_job_when_auto_is_on(): void
    {
        Bus::fake();
        $order = Order::factory()->create(['payment_status' => Order::STATUS_PENDING, 'billing_name' => 'X', 'billing_country' => 'HU']);

        app(CheckoutService::class)->markPaid($order);

        Bus::assertDispatched(IssueInvoiceJob::class, fn ($job) => $job->orderId === $order->id);
    }

    public function test_admin_can_storno_an_invoice(): void
    {
        $this->fakeBillingo();
        $order = $this->paidOrder();
        $invoice = app(InvoiceManager::class)->issueFor($order);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->post("/admin/invoices/{$invoice->id}/storno")
            ->assertRedirect();

        $this->assertTrue($order->invoices()->where('type', Invoice::TYPE_STORNO)->where('status', 'issued')->exists());
    }

    public function test_customer_can_download_the_invoice_pdf_with_the_token(): void
    {
        $this->fakeBillingo();
        $order = $this->paidOrder();
        $order->issueDownloadToken();
        app(InvoiceManager::class)->issueFor($order);

        $this->get('/download/'.$order->fresh()->download_token.'/invoice')
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
