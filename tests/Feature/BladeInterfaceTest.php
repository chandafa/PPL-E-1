<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BladeInterfaceTest extends TestCase
{
    public function test_semua_halaman_merender_kerangka_blade_tanpa_data_pajak(): void
    {
        $this->withoutVite();
        foreach (['/login', '/tax-invoices', '/tax-invoices/create', '/tax-invoices/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', '/tax-recaps/2026-11', '/tax-recaps/2026-11/payments/create'] as $path) {
            $this->get($path)->assertOk()->assertSee('e1-config')->assertSee('solar-document', false);
        }
        $this->get('/tax-recaps/2026-13')->assertNotFound();
        $this->get('/')->assertRedirect('/tax-invoices');
    }

    public function test_login_demo_tidak_ditampilkan_di_production_atau_saat_nonaktif(): void
    {
        $this->withoutVite();
        config(['e1.dev_login.enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('id="demo-login"', false);
        config(['e1.dev_login.enabled' => true]);
        $this->app['env'] = 'production';
        $this->get('/login')->assertOk()->assertDontSee('id="demo-login"', false);
    }

    public function test_login_hanya_menampilkan_form_akun_demo(): void
    {
        $this->withoutVite();
        config(['e1.dev_login.enabled' => true]);
        $this->get('/login')->assertOk()
            ->assertSee('id="demo-login"', false)
            ->assertDontSee('id="token-login"', false)
            ->assertDontSee('Token akses F-1')
            ->assertDontSee('<details', false);
    }

    public function test_rebuild_memperbaiki_rekap_lama_dan_idempoten(): void
    {
        $f = $this->createFaktur();
        $this->createFaktur('inv-demo-001', ['dpp' => '90000000.00', 'ppn' => '9900000.00'], $f['taxInvoiceId']);
        DB::table('rekap_pajak_bulanan')->update(['invoice_count' => 0, 'total_dpp' => '0.00', 'total_ppn' => '0.00', 'payment_difference' => '0.00']);
        foreach ([1, 2] as $run) {
            $this->artisan('e1:recaps:rebuild')->expectsOutput('1 masa pajak dihitung ulang.')->assertSuccessful();
            $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'invoice_count' => 1, 'total_dpp' => '90000000.00', 'total_ppn' => '9900000.00']);
        }
    }
}
