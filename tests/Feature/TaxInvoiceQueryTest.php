<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Services\Invoices\InvoiceProvider;
use Tests\TestCase;

/** API-KE01-03 (cari/filter), API-KE01-04 (detail), API-KE01-06 (riwayat). */
class TaxInvoiceQueryTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $f = [];

    protected function setUp(): void
    {
        parent::setUp();

        // A,B,C masa 2026-11; D masa 2026-12. C dibatalkan.
        $this->f['A'] = $this->createFaktur('inv-demo-001', ['nsfp' => '010.000-26.00000101', 'date' => '1 November 2026']);
        $this->f['B'] = $this->createFaktur('inv-demo-002', ['nsfp' => '010.000-26.00000102', 'date' => '5 November 2026', 'dpp' => '250000000.00', 'ppn' => '27500000.00']);
        $this->f['C'] = $this->createFaktur('inv-demo-003', ['nsfp' => '010.000-26.00000103', 'date' => '12 November 2026', 'dpp' => '75000000.00', 'ppn' => '8250000.00']);
        $this->f['D'] = $this->createFaktur('inv-demo-004', ['nsfp' => '010.000-26.00000104', 'date' => '2 Desember 2026', 'dpp' => '120000000.00', 'ppn' => '13200000.00']);

        $this->patchJson('/api/v1/tax-invoices/'.$this->f['C']['taxInvoiceId'], ['changes' => ['status' => 'CANCELLED']], $this->auth())->assertOk();
    }

    private function numbers(string $query = ''): array
    {
        $response = $this->getJson('/api/v1/tax-invoices'.$query, $this->auth());
        $response->assertOk();

        return array_map(fn ($i) => substr($i['taxInvoiceNumber'], -3), $response->json('data.items'));
    }

    public function test_daftar_tanpa_filter_terurut_tanggal_terbaru_dan_berpaging(): void
    {
        $response = $this->getJson('/api/v1/tax-invoices', $this->auth());

        $response->assertOk()
            ->assertJsonPath('code', '200')
            ->assertJsonPath('message', 'Daftar faktur')
            ->assertJsonPath('data.pagination', ['page' => 1, 'pageSize' => 20, 'totalItems' => 4]);

        $this->assertSame(['104', '103', '102', '101'], $this->numbers());

        $first = $response->json('data.items.0');
        $this->assertEqualsCanonicalizing([
            'taxInvoiceId', 'type', 'taxInvoiceNumber', 'taxInvoiceDate', 'invoiceId', 'projectId', 'clientId', 'vendorName',
            'taxPeriod', 'dpp', 'ppn', 'status', 'replacedByTaxInvoiceId',
        ], array_keys($first));
    }

    public function test_filter_masa_pajak_dan_status(): void
    {
        $this->assertSame(['104'], $this->numbers('?taxPeriod=2026-12'));
        $this->assertSame(['103'], $this->numbers('?status=CANCELLED'));
        $this->assertSame(['104', '102', '101'], $this->numbers('?status=NORMAL'));
        $this->assertSame(['102', '101'], $this->numbers('?taxPeriod=2026-11&status=NORMAL'));
    }

    public function test_filter_proyek_dan_klien_menurut_id_atau_nama(): void
    {
        $this->assertSame(['103', '102'], $this->numbers('?project=prj-demo-002'));
        $this->assertSame(['103', '102'], $this->numbers('?project=gudang'));
        $this->assertSame(['103', '102'], $this->numbers('?client=lintas'));
        $this->assertSame(['103', '102'], $this->numbers('?client=0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c01'));
        $this->assertSame(['104'], $this->numbers('?client=Samudra'));
    }

    public function test_filter_nomor_faktur_sebagian_dan_karakter_khusus_aman(): void
    {
        $this->assertSame(['102'], $this->numbers('?taxInvoiceNumber=00000102'));
        $this->assertSame(['104', '103', '102', '101'], $this->numbers('?taxInvoiceNumber=010.000-26'));
        $this->assertSame([], $this->numbers('?taxInvoiceNumber=%25'));
        $this->assertSame([], $this->numbers('?taxInvoiceNumber=_'));
    }

    public function test_paging_menghitung_total_dan_halaman(): void
    {
        $page2 = $this->getJson('/api/v1/tax-invoices?pageSize=3&page=2', $this->auth());

        $page2->assertOk()->assertJsonPath('data.pagination', ['page' => 2, 'pageSize' => 3, 'totalItems' => 4]);
        $this->assertCount(1, $page2->json('data.items'));
    }

    public function test_tidak_ada_hasil_tetap_200_dengan_daftar_kosong(): void
    {
        $this->getJson('/api/v1/tax-invoices?project=tidak-ada', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.pagination.totalItems', 0);
    }

    public function test_filter_tidak_valid_400(): void
    {
        foreach (['taxPeriod=2026-13', 'taxPeriod=November', 'status=APPROVED', 'status=normal', 'page=0', 'page=abc', 'pageSize=0', 'pageSize=101'] as $query) {
            $this->getJson('/api/v1/tax-invoices?'.$query, $this->auth())
                ->assertStatus(400)
                ->assertJsonPath('code', '400')
                ->assertJsonPath('errorCode', 'INVALID_FILTER');
        }
    }

    public function test_detail_tetap_dapat_dibaca_saat_referensi_d2_tidak_tersedia(): void
    {
        $provider = \Mockery::mock(InvoiceProvider::class);
        $provider->shouldReceive('find')->once()->andThrow(new ApiException(503, 'INVOICE_SERVICE_UNAVAILABLE', 'D-2 tidak tersedia'));
        $this->app->instance(InvoiceProvider::class, $provider);
        $this->getJson('/api/v1/tax-invoices/'.$this->f['A']['taxInvoiceId'], $this->auth())->assertOk()
            ->assertJsonPath('data.invoiceReference', null)
            ->assertJsonPath('data.taxInvoice.taxPeriod', '2026-11');
    }

    public function test_detail_memuat_item_dan_tautan_asal_pengganti(): void
    {
        $replacement = $this->createFaktur('inv-demo-005', ['nsfp' => '010.000-26.00000201'], null);
        $replacement2 = $this->createFaktur('inv-demo-005', ['nsfp' => '010.000-26.00000202'], $replacement['taxInvoiceId']);

        $detail = $this->getJson('/api/v1/tax-invoices/'.$replacement['taxInvoiceId'], $this->auth());
        $detail->assertOk()
            ->assertJsonPath('message', 'Detail faktur')
            ->assertJsonPath('data.taxInvoice.taxInvoiceId', $replacement['taxInvoiceId'])
            ->assertJsonPath('data.taxInvoice.status', 'NORMAL')
            ->assertJsonPath('data.replacesTaxInvoiceId', null)
            ->assertJsonPath('data.replacedByTaxInvoiceId', $replacement2['taxInvoiceId'])
            ->assertJsonCount(1, 'data.items');

        $this->getJson('/api/v1/tax-invoices/'.$replacement2['taxInvoiceId'], $this->auth())
            ->assertOk()
            ->assertJsonPath('data.taxInvoice.status', 'REPLACEMENT')
            ->assertJsonPath('data.replacesTaxInvoiceId', $replacement['taxInvoiceId'])
            ->assertJsonPath('data.replacedByTaxInvoiceId', null);
    }

    public function test_detail_dan_riwayat_id_tidak_dikenal_404(): void
    {
        foreach (['0191f3a0-7c1e-7a52-9d3b-4f6e2a1b8c99', 'bukan-uuid', '123'] as $id) {
            $this->getJson('/api/v1/tax-invoices/'.$id, $this->auth())
                ->assertStatus(404)
                ->assertExactJson([
                    'code' => '404',
                    'message' => 'Faktur pajak tidak ditemukan',
                    'data' => null,
                    'errorCode' => 'TAX_INVOICE_NOT_FOUND',
                ]);

            $this->getJson('/api/v1/tax-invoices/'.$id.'/history', $this->auth())
                ->assertStatus(404)->assertJsonPath('errorCode', 'TAX_INVOICE_NOT_FOUND');
        }
    }

    public function test_riwayat_kronologis_dengan_sebelum_dan_sesudah(): void
    {
        $id = $this->f['A']['taxInvoiceId'];

        $this->patchJson('/api/v1/tax-invoices/'.$id, [
            'changes' => ['taxInvoiceDate' => '2026-11-10', 'dpp' => '99000000.00'], 'note' => 'Koreksi tanggal dan DPP',
        ], $this->auth('COO', 'AKN-0005'))->assertOk();
        $this->patchJson('/api/v1/tax-invoices/'.$id, ['changes' => ['status' => 'CANCELLED'], 'note' => 'Salah klien'], $this->auth('CEO', 'AKN-0006'))->assertOk();

        $history = $this->getJson('/api/v1/tax-invoices/'.$id.'/history', $this->auth())
            ->assertOk()->assertJsonPath('message', 'Riwayat faktur')->json('data.history');

        $this->assertSame(['CREATE', 'UPDATE', 'STATUS_CHANGE'], array_column($history, 'action'));
        $this->assertSame(['AKN-0002', 'AKN-0005', 'AKN-0006'], array_column($history, 'actorAkunId'));

        $this->assertNull($history[0]['beforeSnapshot']);
        $this->assertSame('010.000-26.00000101', $history[0]['afterSnapshot']['taxInvoiceNumber']);

        $this->assertSame(['taxInvoiceDate' => '2026-11-01', 'dpp' => '100000000.00'], $history[1]['beforeSnapshot']);
        $this->assertSame(['taxInvoiceDate' => '2026-11-10', 'dpp' => '99000000.00'], $history[1]['afterSnapshot']);
        $this->assertSame('Koreksi tanggal dan DPP', $history[1]['note']);

        $this->assertSame(['status' => 'NORMAL'], $history[2]['beforeSnapshot']);
        $this->assertSame(['status' => 'CANCELLED'], $history[2]['afterSnapshot']);
        $this->assertSame('Salah klien', $history[2]['note']);

        foreach ($history as $entry) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $entry['changedAt']);
        }
    }

    public function test_peran_tidak_berwenang_tidak_melihat_data(): void
    {
        $id = $this->f['A']['taxInvoiceId'];

        foreach (['/api/v1/tax-invoices', '/api/v1/tax-invoices/'.$id, '/api/v1/tax-invoices/'.$id.'/history'] as $uri) {
            $this->getJson($uri, $this->auth('Karyawan'))
                ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN')->assertJsonPath('data', null);
        }
    }
}
