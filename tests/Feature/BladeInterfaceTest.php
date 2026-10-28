<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BladeInterfaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Tests\Support\InvoiceScenarioSeeder::class);
    }

    public function test_semua_halaman_merender_kerangka_blade_tanpa_data_pajak(): void
    {
        $this->withoutVite();
        foreach (['/login', '/tax-invoices', '/tax-invoices/create', '/tax-invoices/0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', '/d2-invoices', '/tax-recaps/2026-11', '/tax-recaps/2026-11/payments/create'] as $path) {
            $this->get($path)->assertOk()->assertSee('e1-config')->assertSee('solar-document', false);
        }
        $this->get('/tax-recaps/2026-13')->assertNotFound();
        $this->get('/')->assertRedirect('/tax-invoices');
    }

    public function test_login_meminta_kredensial_f1_tanpa_mengizinkan_pemilihan_peran(): void
    {
        $this->withoutVite();
        config(['e1.dev_login.enabled' => true]);
        $this->get('/login')->assertOk()
            ->assertSee('id="f1-login"', false)
            ->assertSee('Login')
            ->assertDontSee('Login F-1')
            ->assertSee('name="emailAtauUsername"', false)
            ->assertSee('name="password"', false)
            ->assertSee('placeholder="Masukkan username"', false)
            ->assertSee('placeholder="Masukkan password"', false)
            ->assertDontSee('name="kodePeran"', false)
            ->assertDontSee('Akun Demo');
    }

    public function test_rebuild_memperbaiki_rekap_lama_dan_idempoten(): void
    {
        $f = $this->createFaktur();
        $this->createFaktur('inv-demo-sample', ['dpp' => '90000000.00', 'ppn' => '9900000.00'], $f['taxInvoiceId']);
        DB::table('rekap_pajak_bulanan')->update(['invoice_count' => 0, 'total_dpp' => '0.00', 'total_ppn' => '0.00', 'payment_difference' => '0.00']);
        foreach ([1, 2] as $run) {
            $this->artisan('e1:recaps:rebuild')->expectsOutput('1 masa pajak dihitung ulang.')->assertSuccessful();
            $this->assertDatabaseHas('rekap_pajak_bulanan', ['tax_period' => '2026-11', 'invoice_count' => 1, 'total_dpp' => '90000000.00', 'total_ppn' => '9900000.00']);
        }
    }
}
