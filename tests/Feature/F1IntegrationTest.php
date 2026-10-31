<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\OpenApiContract;
use Tests\TestCase;

class F1IntegrationTest extends TestCase
{
    private const ACCOUNT = '01929b4a-6e2a-7b3f-8c71-0ab4e3d9f2a1';

    private const BASE = 'http://f1.test';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'e1.f1.api_enabled' => true, 'e1.f1.base_url' => self::BASE,
            'e1.f1.check_account' => true,
            'e1.f1.claim_akun_id' => 'accountId', 'e1.f1.claim_role' => 'roleCode',
            'e1.f1.issuer' => '', 'e1.f1.audience' => '',
        ]);
        Http::preventStrayRequests();
    }

    private function f1Token(array $changes = []): string
    {
        return JWT::encode(array_merge([
            'accountId' => self::ACCOUNT, 'roleCode' => 'ADMIN', 'iat' => time(), 'exp' => time() + 3600,
        ], $changes), (string) config('e1.f1.secret'), 'HS256');
    }

    private function loginResponse(array $changes = []): array
    {
        return array_merge([
            'accessToken' => $this->f1Token(), 'tokenType' => 'Bearer', 'expiresIn' => 3600,
            'accountId' => self::ACCOUNT, 'roleCode' => 'ADMIN',
        ], $changes);
    }

    private function login(array $changes = [])
    {
        return $this->postJson('/api/auth/f1/login', array_merge([
            'emailOrUsername' => 'super.admin', 'password' => 'local-test-password',
        ], $changes));
    }

    public function test_login_meneruskan_kredensial_ke_f1_dan_jwt_dipakai_di_api_bisnis(): void
    {
        $data = $this->loginResponse();
        Http::fake([
            self::BASE.'/auth/login' => Http::response($data),
            self::BASE.'/accounts/'.self::ACCOUNT.'/role' => Http::response([
                'accountId' => self::ACCOUNT, 'roleCode' => 'ADMIN', 'status' => 'active',
            ]),
        ]);
        $this->login()->assertOk()->assertJsonPath('data.accessToken', $data['accessToken'])
            ->assertJsonPath('data.accountId', self::ACCOUNT)->assertJsonMissingPath('data.password')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$data['accessToken']])->assertOk();
        Http::assertSent(fn ($request) => $request->url() === self::BASE.'/auth/login'
            && $request['emailOrUsername'] === 'super.admin' && $request['password'] === 'local-test-password');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/role')
            && $request->hasHeader('Authorization', 'Bearer '.$data['accessToken']));
    }

    public static function upstreamRejections(): array
    {
        return [[401, 401, 'INVALID_CREDENTIALS'], [403, 403, 'F1_ACCOUNT_BLOCKED'],
            [500, 503, 'F1_UNAVAILABLE'], [302, 503, 'F1_UNAVAILABLE']];
    }

    #[DataProvider('upstreamRejections')]
    public function test_kesalahan_f1_dipetakan_tanpa_meneruskan_body_atau_rahasia(int $upstream, int $status, string $code): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['password' => 'secret-upstream'], $upstream)]);
        $this->login()->assertStatus($status)->assertJsonPath('errorCode', $code)->assertJsonPath('data', null)
            ->assertDontSee('secret-upstream');
        Http::assertSentCount(1);
    }

    public function test_koneksi_f1_gagal_503_tanpa_token_tiruan_pengganti(): void
    {
        Http::fake(fn () => throw new ConnectionException('test'));
        $this->login()->assertStatus(503)->assertJsonPath('errorCode', 'F1_UNAVAILABLE')->assertJsonPath('data', null);
    }

    public function test_token_placeholder_tanda_tangan_rusak_dan_expired_ditolak(): void
    {
        foreach (['placeholder-token', $this->f1Token().'x', $this->f1Token(['exp' => time() - 60, 'iat' => time() - 3600])] as $token) {
            Http::fake([self::BASE.'/*' => Http::response($this->loginResponse(['accessToken' => $token]))]);
            $this->login()->assertStatus(502)->assertJsonPath('errorCode', 'F1_INVALID_TOKEN')->assertJsonPath('data', null);
        }
    }

    public function test_identitas_login_harus_cocok_dengan_klaim_token(): void
    {
        Http::fake([self::BASE.'/*' => Http::response($this->loginResponse(['accountId' => 'akun-berbeda']))]);
        $this->login()->assertStatus(502)->assertJsonPath('errorCode', 'F1_INVALID_RESPONSE');
    }

    public function test_iss_dan_aud_tetap_diperiksa_bila_dipersyaratkan_profil(): void
    {
        config(['e1.f1.issuer' => 'f1-identity', 'e1.f1.audience' => 'erp-anaheim']);
        Http::fake([self::BASE.'/*' => Http::response($this->loginResponse())]);
        $this->login()->assertStatus(502)->assertJsonPath('errorCode', 'F1_INVALID_TOKEN');
    }

    public static function roleStates(): array
    {
        return [
            ['ADMIN', 'active', 200], ['COO', 'active', 200], ['EMPLOYEE', 'active', 403],
            ['ADMIN', 'inactive', 403], ['ADMIN', 'locked', 403],
        ];
    }

    #[DataProvider('roleStates')]
    public function test_akses_memakai_status_dan_peran_terkini_bukan_klaim_lama(string $role, string $state, int $status): void
    {
        Http::fake([self::BASE.'/*' => Http::response([
            'accountId' => self::ACCOUNT, 'roleCode' => $role, 'status' => $state,
        ])]);
        $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$this->f1Token()])->assertStatus($status);
        $this->assertDatabaseCount('faktur_pajak', 0);
    }

    public function test_identitas_peran_berbeda_tidak_dipercaya(): void
    {
        Http::fake([self::BASE.'/*' => Http::response([
            'accountId' => 'akun-lain', 'roleCode' => 'ADMIN', 'status' => 'active',
        ])]);
        $response = $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$this->f1Token()])
            ->assertStatus(502)->assertJsonPath('errorCode', 'F1_INVALID_RESPONSE');
        $this->assertSame([], (new OpenApiContract)->validateResponse('listTaxInvoices', 502, json_decode($response->getContent())));
    }

    public function test_token_rusak_ditolak_sebelum_panggilan_f1(): void
    {
        Http::fake();
        $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$this->f1Token().'x'])->assertStatus(401);
        Http::assertNothingSent();
    }

    public function test_pemeriksaan_peran_gagal_tidak_memakai_peran_token_sebagai_fallback(): void
    {
        Http::fake([self::BASE.'/*' => Http::response([], 500)]);
        $response = $this->getJson('/api/v1/tax-invoices', ['Authorization' => 'Bearer '.$this->f1Token()])
            ->assertStatus(503)->assertJsonPath('errorCode', 'F1_UNAVAILABLE');
        $this->assertSame([], (new OpenApiContract)->validateResponse('listTaxInvoices', 503, json_decode($response->getContent())));
    }

    public function test_login_nonaktif_dan_input_tidak_valid_tidak_memanggil_f1(): void
    {
        Http::fake();
        $this->login(['emailOrUsername' => ['bad']])->assertStatus(400)->assertJsonPath('errorCode', 'INVALID_LOGIN');
        config(['e1.f1.api_enabled' => false]);
        $this->login()->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_respons_login_tidak_valid_ditolak(): void
    {
        Http::fake([self::BASE.'/*' => Http::response(['accessToken' => 'anything'])]);
        $this->login()->assertStatus(502)->assertJsonPath('errorCode', 'F1_INVALID_RESPONSE');
    }

    public function test_form_f1_tampil_hanya_saat_integrasi_aktif(): void
    {
        $this->withoutVite();
        $this->get('/login')->assertOk()->assertSee('id="f1-login"', false)->assertSee('autocomplete="current-password"', false);
        config(['e1.f1.api_enabled' => false]);
        $this->get('/login')->assertOk()->assertDontSee('id="f1-login"', false);
    }

    public function test_route_tiruan_f1_dan_d2_tidak_lagi_disediakan_e1(): void
    {
        $this->postJson('/api/auth/login')->assertNotFound();
        $this->getJson('/api/v1/invoices/inv-demo-001')->assertNotFound();
        $this->postJson('/auth/login')->assertNotFound();
    }
}
