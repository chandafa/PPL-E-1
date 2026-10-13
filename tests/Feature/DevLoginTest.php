<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Login tiruan F-1 untuk Postman (di luar kontrak; hanya lokal). */
class DevLoginTest extends TestCase
{
    private const URI = '/api/dev/f1/login';

    public function test_login_tiruan_menerbitkan_token_yang_diterima_api(): void
    {
        $response = $this->postJson(self::URI, ['akunId' => 'AKN-0002', 'kodePeran' => 'Admin']);

        $response->assertOk()
            ->assertJsonPath('code', '200')
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonPath('data.akunId', 'AKN-0002')
            ->assertJsonPath('data.kodePeran', 'Admin')
            ->assertJsonPath('data.expiresIn', 3600);

        $token = $response->json('data.accessToken');

        $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$token])->assertOk();
    }

    public function test_peran_selain_admin_ceo_coo_tetap_ditolak_api(): void
    {
        $token = $this->postJson(self::URI, ['akunId' => 'AKN-0009', 'kodePeran' => 'CTO'])->json('data.accessToken');

        $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$token])
            ->assertStatus(403)->assertJsonPath('errorCode', 'FORBIDDEN');
    }

    public function test_masukan_tidak_valid_400(): void
    {
        foreach ([
            [],
            ['akunId' => 'AKN-0002'],
            ['kodePeran' => 'Admin'],
            ['akunId' => '', 'kodePeran' => 'Admin'],
            ['akunId' => 'AKN-0002', 'kodePeran' => 'Admin', 'ttl' => 5],
            ['akunId' => 'AKN-0002', 'kodePeran' => 'Admin', 'ttl' => 999999],
        ] as $body) {
            $this->postJson(self::URI, $body)
                ->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_LOGIN');
        }
    }

    public function test_dimatikan_berperilaku_seperti_rute_tidak_ada(): void
    {
        config(['e1.dev_login.enabled' => false]);

        $this->postJson(self::URI, ['akunId' => 'AKN-0002', 'kodePeran' => 'Admin'])
            ->assertStatus(404)->assertJsonPath('errorCode', 'NOT_FOUND');
    }

    public function test_tidak_pernah_aktif_di_production(): void
    {
        $this->app['env'] = 'production';

        $this->postJson(self::URI, ['akunId' => 'AKN-0002', 'kodePeran' => 'Admin'])
            ->assertStatus(404)->assertJsonPath('errorCode', 'NOT_FOUND');
    }
}
