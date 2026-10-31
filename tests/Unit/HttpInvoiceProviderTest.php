<?php

namespace Tests\Unit;

use App\Exceptions\ApiException;
use App\Services\Invoices\HttpInvoiceProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HttpInvoiceProviderTest extends TestCase
{
    private const BASE = 'http://localhost:8000';

    private function provider(?string $token = 'token-pemanggil-e1'): HttpInvoiceProvider
    {
        return new HttpInvoiceProvider(
            baseUrl: self::BASE,
            invoicePath: '/api/v1/invoices/{invoiceId}',
            timeout: 5,
            bearerToken: $token,
        );
    }

    private function invoiceArray(): array
    {
        return [
            'invoiceId' => 'inv-demo-001',
            'invoiceNumber' => 'INV-2026-0001',
            'invoiceDate' => '2026-11-01',
            'dppAmount' => '100000000.00',
            'ppnAmount' => '11000000.00',
            'status' => 'TERTAGIH',
            'projectId' => 'prj-demo-001',
            'projectName' => 'Pengembangan Portal ERP Tahap 1',
            'clientId' => null,
            'clientName' => null,
        ];
    }

    public function test_mengambil_invoice_dari_d2_lewat_http(): void
    {
        Http::fake([self::BASE.'/api/v1/invoices/inv-demo-001' => Http::response([
            'status' => 'success',
            'message' => 'Detail invoice berhasil dimuat',
            'data' => $this->invoiceArray(),
        ], 200)]);

        $data = $this->provider()->find('inv-demo-001');

        $this->assertNotNull($data);
        $this->assertSame('inv-demo-001', $data->invoiceId);
        $this->assertSame('INV-2026-0001', $data->invoiceNumber);
        $this->assertSame('2026-11-01', $data->invoiceDate);
        $this->assertSame('100000000.00', $data->dppAmount);
        $this->assertSame('11000000.00', $data->ppnAmount);
        $this->assertSame('TERTAGIH', $data->status);
        $this->assertSame('prj-demo-001', $data->projectId);
        $this->assertSame('Pengembangan Portal ERP Tahap 1', $data->projectName);
        $this->assertNull($data->clientId);
    }

    public function test_berhasil_bila_d2_kembalikan_objek_invoice_tanpa_pembungkus_data(): void
    {
        $row = $this->invoiceArray();
        $row['invoiceId'] = 'inv-demo-002';

        Http::fake([self::BASE.'/api/v1/invoices/inv-demo-002' => Http::response($row, 200)]);

        $data = $this->provider()->find('inv-demo-002');

        $this->assertNotNull($data);
        $this->assertSame('inv-demo-002', $data->invoiceId);
    }

    public function test_invoice_tidak_ditemukan_mengembalikan_null(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['status' => 'error', 'code' => 'INVOICE_NOT_FOUND'], 404)]);

        $this->assertNull($this->provider()->find('inv-tidak-ada'));
    }

    public function test_d2_tidak_dapat_dihubungi_lempar_503(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['status' => 'error'], 502)]);

        $this->expectException(ApiException::class);
        $this->provider()->find('inv-demo-001');
    }

    public function test_koneksi_ditolak_lempar_503(): void
    {
        Http::fake(function () {
            throw new ConnectionException('Connection refused', 0);
        });

        $this->expectException(ApiException::class);
        $this->provider()->find('inv-demo-001');
    }

    public function test_id_invoice_tidak_cocok_lempar_503(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['status' => 'success', 'data' => $this->invoiceArray()], 200)]);

        $this->expectException(ApiException::class);
        $this->provider()->find('inv-lainnya');
    }

    public function test_field_bukan_string_lempar_503(): void
    {
        $row = $this->invoiceArray();
        $row['status'] = 123;

        Http::fake([self::BASE.'/*' => Http::response(['status' => 'success', 'data' => $row], 200)]);

        $this->expectException(ApiException::class);
        $this->provider()->find('inv-demo-001');
    }

    public function test_header_authorisasi_dikirim_ke_d2(): void
    {
        Http::fake([self::BASE.'/api/v1/invoices/inv-demo-001' => function ($request) {
            $this->assertSame('Bearer token-pemanggil-e1', Arr::first($request->header('Authorization')));

            return Http::response(['status' => 'success', 'data' => $this->invoiceArray()], 200);
        }]);

        $this->assertNotNull($this->provider()->find('inv-demo-001'));
    }

    public function test_tanpa_token_bearer_masih_memanggil_d2_butuh_d2_terima(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['status' => 'success', 'data' => $this->invoiceArray()], 200)]);

        $this->assertNotNull($this->provider(null)->find('inv-demo-001'));
    }

    public function test_katalog_d2_http_kosong_tanpa_endpoint_daftar(): void
    {
        $this->assertSame([], $this->provider()->catalog());
    }

    public function test_struktur_terkait_resmi_d2_mempertahankan_id_proyek_tanpa_mengarang_klien(): void
    {
        $row = $this->invoiceArray();
        unset($row['projectId'], $row['projectName']);
        $row['terkait'] = ['projectId' => 'prj-demo-001', 'terminId' => 'trm-demo-sample'];
        Http::fake([self::BASE.'/*' => Http::response(['status' => 'success', 'data' => $row])]);
        $invoice = $this->provider()->find('inv-demo-001');
        $this->assertSame('prj-demo-001', $invoice->projectId);
        $this->assertNull($invoice->projectName);
        $this->assertNull($invoice->clientId);
    }

    public function test_id_proyek_terkait_yang_salah_tipe_atau_bertentangan_ditolak(): void
    {
        foreach ([123, ['bad'], 'prj-salah'] as $value) {
            $row = $this->invoiceArray();
            $row['terkait'] = ['projectId' => $value];
            Http::fake([self::BASE.'/*' => Http::response(['data' => $row])]);
            try {
                $this->provider()->find('inv-demo-001');
                $this->fail('Respons tidak valid seharusnya ditolak');
            } catch (ApiException $e) {
                $this->assertSame('D2_UNAVAILABLE', $e->errorCode);
            }
        }
    }

    public function test_kesalahan_otorisasi_dan_redirect_d2_tidak_diberi_data_mock(): void
    {
        foreach ([401, 403, 302] as $status) {
            Http::fake([self::BASE.'/*' => Http::response([], $status)]);
            try {
                $this->provider()->find('inv-demo-001');
                $this->fail('Upstream gagal harus ditolak');
            } catch (ApiException $e) {
                $this->assertSame(503, $e->status);
            }
        }
    }
}
