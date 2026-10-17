<?php

namespace Tests\Feature;

use App\Services\Auth\DevTokenIssuer;
use Database\Seeders\Support\DemoDataset;
use Tests\TestCase;

class DemoInvoiceCatalogTest extends TestCase
{
    private function headers(string $role = 'Admin'): array
    {
        $issued = app(DevTokenIssuer::class)->issue('AKN-0002', $role);

        return ['Authorization' => 'Bearer '.$issued['token']];
    }

    public function test_manual_upload_invoice_is_available_but_excluded_from_seeder(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonFragment(['invoiceId' => 'inv-manual-001', 'manualUploadOnly' => true]);
        $this->assertArrayNotHasKey('inv-manual-001', DemoDataset::invoices());
        $this->assertArrayNotHasKey('inv-manual-001', DemoDataset::seededInvoices());
        $this->assertCount(31, DemoDataset::seededInvoices());
    }

    public function test_catalog_uses_the_same_dummy_data_as_d2(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $expected = json_decode(file_get_contents(config('e1.d2.mock_file')), true, flags: JSON_THROW_ON_ERROR);

        $this->getJson('/api/ui/d2/invoices', $this->headers())
            ->assertOk()->assertJsonPath('data.invoices', $expected);
    }

    public function test_catalog_requires_an_authorized_f1_identity(): void
    {
        $this->getJson('/api/ui/d2/invoices')->assertUnauthorized();
        $this->getJson('/api/ui/d2/invoices', $this->headers('CTO'))->assertForbidden();
    }

    public function test_active_invoices_disappear_and_cancelled_invoices_return(): void
    {
        $original = $this->createFaktur('inv-demo-001');
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonMissing(['invoiceId' => 'inv-demo-001']);
        $replacement = $this->createFaktur('inv-demo-001', [], $original['taxInvoiceId']);
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonMissing(['invoiceId' => 'inv-demo-001']);
        $this->patchJson('/api/v1/tax-invoices/'.$replacement['taxInvoiceId'], [
            'changes' => ['status' => 'CANCELLED'], 'note' => 'Uji invoice tersedia kembali',
        ], $this->headers())->assertOk();
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonMissing(['invoiceId' => 'inv-demo-001']);
        $this->patchJson('/api/v1/tax-invoices/'.$original['taxInvoiceId'], [
            'changes' => ['status' => 'CANCELLED'], 'note' => 'Batalkan juga faktur asal yang aktif kembali',
        ], $this->headers())->assertOk();
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertOk()
            ->assertJsonFragment(['invoiceId' => 'inv-demo-001']);
    }

    public function test_http_driver_does_not_offer_dummy_invoices(): void
    {
        config(['e1.d2.driver' => 'http']);
        $this->getJson('/api/ui/d2/invoices', $this->headers())->assertNotFound();
        $this->withoutVite()->get('/tax-invoices/create')
            ->assertOk()->assertSee('placeholder="Masukkan ID invoice"', false)
            ->assertDontSee('id="invoice-context"', false);
    }

    public function test_mock_form_renders_a_required_invoice_select(): void
    {
        config(['e1.d2.driver' => 'mock']);
        $this->withoutVite()->get('/tax-invoices/create')
            ->assertOk()->assertSee('<select id="invoiceId"', false)
            ->assertSee('Data dummy D-2')->assertSee('id="invoice-context"', false);
    }
}
