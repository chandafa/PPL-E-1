<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Services\Auth\DevTokenIssuer;
use App\Services\Invoices\IntegrationCatalog;
use Database\Seeders\ModulD2Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DemoInvoiceCatalogTest extends TestCase
{
    private function headers(string $role = 'Admin'): array
    {
        $issued = app(DevTokenIssuer::class)->issue('AKN-0002', $role);

        return ['Authorization' => 'Bearer '.$issued['token']];
    }

    public function test_database_contains_the_current_d2_reference_records(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $this->assertDatabaseCount('contracts', 3);
        $this->assertDatabaseCount('termins', 4);
        $this->assertDatabaseCount('tax_parameters', 1);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_histories', 1);
        $this->assertContains('project_name', Schema::getColumnListing('contracts'));
        $this->assertNotContains('client_id', Schema::getColumnListing('invoices'));
        $this->assertNotContains('client_name', Schema::getColumnListing('invoices'));
        $this->assertDatabaseHas('invoices', [
            'invoice_id' => 'inv-demo-sample',
            'invoice_number' => 'INV-2026-SAMPLE',
            'dpp_amount' => 100000000,
            'ppn_amount' => 11000000,
        ]);
        $this->seed(ModulD2Seeder::class);
        $this->assertDatabaseCount('contracts', 3);
        $this->assertDatabaseCount('termins', 4);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('invoice_histories', 1);
    }

    public function test_catalog_is_read_from_the_seeded_d2_invoice_tables(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $response = $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk();
        $this->assertSame(
            Invoice::query()->orderBy('invoice_id')->pluck('invoice_id')->all(),
            array_column($response->json('data.invoices'), 'invoiceId'),
        );
        $response->assertJsonPath('data.invoices.0.invoiceId', 'inv-demo-sample')
            ->assertJsonPath('data.invoices.0.projectId', 'prj-demo-001')
            ->assertJsonPath('data.invoices.0.projectName', null)
            ->assertJsonPath('data.invoices.0.clientId', null)
            ->assertJsonPath('data.invoices.0.clientName', null);
    }

    public function test_catalog_requires_an_authorized_f1_identity(): void
    {
        $this->getJson('/api/ui/d2/invoices')->assertUnauthorized();
        $this->getJson('/api/ui/d2/invoices', $this->headers('CTO'))->assertForbidden();
    }

    public function test_active_invoices_disappear_and_cancelled_invoices_return(): void
    {
        $original = $this->createFaktur('inv-demo-sample');
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonMissing(['invoiceId' => 'inv-demo-sample']);
        $replacement = $this->createFaktur('inv-demo-sample', [], $original['taxInvoiceId']);
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonMissing(['invoiceId' => 'inv-demo-sample']);
        $this->patchJson('/api/v1/tax-invoices/'.$replacement['taxInvoiceId'], [
            'changes' => ['status' => 'CANCELLED'], 'note' => 'Uji invoice tersedia kembali',
        ], $this->headers())->assertOk();
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonMissing(['invoiceId' => 'inv-demo-sample']);
        $this->patchJson('/api/v1/tax-invoices/'.$original['taxInvoiceId'], [
            'changes' => ['status' => 'CANCELLED'], 'note' => 'Batalkan juga faktur asal yang aktif kembali',
        ], $this->headers())->assertOk();
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonFragment(['invoiceId' => 'inv-demo-sample']);
    }

    public function test_http_driver_offers_verified_references_and_keeps_manual_id_form(): void
    {
        config(['e1.d2.driver' => 'http', 'e1.d2.base_url' => 'https://d2.test', 'e1.d2.integration_catalog' => false]);
        Http::fake(['https://d2.test/*' => Http::response(['data' => [
            'invoiceId' => 'inv-demo-sample', 'invoiceNumber' => 'FROM-D2', 'dppAmount' => '12.00',
        ]])]);
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonPath('data.invoices.0.invoiceNumber', 'FROM-D2')
            ->assertJsonPath('data.invoices.0.dppAmount', '12.00');
        $this->withoutVite()->get('/tax-invoices/create')
            ->assertOk()->assertSee('placeholder="Masukkan ID invoice"', false)
            ->assertDontSee('id="invoice-context"', false);
    }

    public function test_local_http_integration_lists_source_ids_and_renders_select(): void
    {
        config(['e1.d2.driver' => 'http', 'e1.d2.base_url' => 'http://127.0.0.1:8026', 'e1.d2.integration_catalog' => true]);
        $catalog = Mockery::mock(IntegrationCatalog::class);
        $catalog->shouldReceive('ids')->once()->andReturn(['new-in-d2']);
        $this->app->instance(IntegrationCatalog::class, $catalog);
        Http::fake(['http://127.0.0.1:8026/*' => Http::response(['data' => ['invoiceId' => 'new-in-d2', 'dppAmount' => '55.00']])]);
        $this->getJson('/api/ui/d2/invoices/all', $this->headers())->assertOk()
            ->assertJsonPath('data.invoices.0.invoiceId', 'new-in-d2')
            ->assertJsonPath('data.invoices.0.dppAmount', '55.00');
        $this->withoutVite()->get('/tax-invoices/create')->assertOk()
            ->assertSee('<select id="invoiceId"', false)->assertSee('id="invoice-context"', false)
            ->assertDontSee('Data dummy D-2');
    }

    public function test_mock_form_renders_a_required_invoice_select(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $this->withoutVite()->get('/tax-invoices/create')
            ->assertOk()->assertSee('<select id="invoiceId"', false)
            ->assertSee('Data dummy D-2')->assertSee('id="invoice-context"', false);
    }
}
