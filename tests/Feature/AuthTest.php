<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Tests\TestCase;

class AuthTest extends TestCase
{
    private const URI = '/api/v1/tax-invoices';

    public function test_tanpa_token_ditolak_401_dengan_amplop_kesalahan(): void
    {
        $this->getJson(self::URI)
            ->assertStatus(401)
            ->assertExactJson([
                'code' => '401',
                'message' => 'Token tidak ada atau tidak sah',
                'data' => null,
                'errorCode' => 'UNAUTHORIZED',
            ]);
    }

    public function test_header_bukan_bearer_ditolak_401(): void
    {
        $this->getJson(self::URI, ['Authorization' => 'Basic abc'])->assertStatus(401);
    }

    public function test_token_dengan_tanda_tangan_salah_ditolak_401(): void
    {
        $forged = $this->token().'x';

        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$forged])
            ->assertStatus(401)->assertJsonPath('errorCode', 'UNAUTHORIZED');
    }

    public function test_token_kedaluwarsa_ditolak_401(): void
    {
        $expired = $this->token('Admin', 'AKN-0002', ['exp' => time() - 60, 'iat' => time() - 3600]);

        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$expired])
            ->assertStatus(401)->assertJsonPath('errorCode', 'UNAUTHORIZED');
    }

    public function test_token_tanpa_exp_ditolak_401(): void
    {
        $payload = ['iss' => 'f1-identity', 'aud' => 'erp-anaheim', 'akunId' => 'AKN-0002', 'kodePeran' => 'Admin'];
        $token = JWT::encode($payload, (string) config('e1.f1.secret'), 'HS256');

        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$token])->assertStatus(401);
    }

    public function test_audience_atau_issuer_salah_ditolak_401(): void
    {
        $wrongAud = $this->token('Admin', 'AKN-0002', ['aud' => 'sistem-lain']);
        $wrongIss = $this->token('Admin', 'AKN-0002', ['iss' => 'penerbit-palsu']);

        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$wrongAud])->assertStatus(401);
        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$wrongIss])->assertStatus(401);
    }

    public function test_token_tanpa_identitas_akun_ditolak_401(): void
    {
        $token = $this->token('Admin', 'AKN-0002', ['akunId' => '']);

        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$token])->assertStatus(401);
    }

    public function test_peran_selain_admin_ceo_coo_ditolak_403(): void
    {
        foreach (['CTO', 'Finance', 'Karyawan'] as $role) {
            $this->getJson(self::URI, $this->auth($role))
                ->assertStatus(403)
                ->assertExactJson([
                    'code' => '403',
                    'message' => 'Pengguna sah, akses tidak cukup',
                    'data' => null,
                    'errorCode' => 'FORBIDDEN',
                ]);
        }
    }

    public function test_token_tanpa_peran_ditolak_403_bukan_401(): void
    {
        $token = $this->token('Admin', 'AKN-0002', ['kodePeran' => null]);

        $this->getJson(self::URI, ['Authorization' => 'Bearer '.$token])
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
    }

    public function test_admin_ceo_coo_diizinkan_termasuk_huruf_besar_kecil(): void
    {
        foreach (['Admin', 'CEO', 'COO', 'admin', 'coo'] as $role) {
            $this->getJson(self::URI, $this->auth($role))->assertOk();
        }
    }

    public function test_peran_layanan_tambahan_hanya_boleh_membaca_ringkasan(): void
    {
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth('LayananE2'))->assertOk();
        $this->getJson(self::URI, $this->auth('LayananE2'))->assertStatus(403);
        $this->getJson('/api/v1/tax/summary?taxPeriod=2026-11', $this->auth('CTO'))
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
    }

    public function test_rute_tidak_dikenal_memakai_amplop_404(): void
    {
        $this->getJson('/api/v1/tidak-ada', $this->auth())
            ->assertStatus(404)
            ->assertJsonPath('code', '404')
            ->assertJsonPath('data', null)
            ->assertJsonPath('errorCode', 'NOT_FOUND');
    }

    public function test_metode_salah_memakai_amplop_405(): void
    {
        $this->deleteJson(self::URI, [], $this->auth())
            ->assertStatus(405)->assertJsonPath('errorCode', 'METHOD_NOT_ALLOWED');
    }
}
