<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class F1LoginTest extends TestCase
{
    public function test_kredensial_diteruskan_ke_f1_dan_peran_diambil_dari_jwt(): void
    {
        config(['e1.f1.base_url' => 'http://127.0.0.1:4100']);

        $token = $this->issueTestToken('COO', 'AKN-F1-001');
        Http::fake([
            'http://127.0.0.1:4100/auth/login' => Http::response([
                'accessToken' => $token,
                'akunId' => 'AKN-F1-001',
                'kodePeran' => 'Admin',
            ]),
        ]);

        $this->postJson('/api/auth/login', [
            'emailAtauUsername' => 'yuril.salsabila',
            'password' => 'kata-sandi-uji',
            'kodePeran' => 'Admin',
        ])->assertOk()
            ->assertJsonPath('data.accessToken', $token)
            ->assertJsonPath('data.akunId', 'AKN-F1-001')
            ->assertJsonPath('data.kodePeran', 'COO');

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'http://127.0.0.1:4100/auth/login'
            && $request['emailAtauUsername'] === 'yuril.salsabila'
            && $request['password'] === 'kata-sandi-uji'
            && ! isset($request['kodePeran']));
    }

    public function test_kredensial_salah_dari_f1_menghasilkan_401(): void
    {
        config(['e1.f1.base_url' => 'http://127.0.0.1:4100']);
        Http::fake(['*' => Http::response(['kode' => 'KREDENSIAL_SALAH'], 401)]);

        $this->postJson('/api/auth/login', [
            'emailAtauUsername' => 'pengguna-tidak-ada',
            'password' => 'kata-sandi-salah',
        ])->assertUnauthorized()
            ->assertJsonPath('errorCode', 'UNAUTHORIZED');
    }

    public function test_akun_yang_tidak_diizinkan_mendapat_pesan_tanpa_label_layanan(): void
    {
        config(['e1.f1.base_url' => 'http://127.0.0.1:4100']);
        Http::fake(['*' => Http::response(['kode' => 'AKUN_TIDAK_DIIZINKAN'], 403)]);

        $this->postJson('/api/auth/login', [
            'emailAtauUsername' => 'icha.aprilia',
            'password' => 'kata-sandi-uji',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Akun tidak diizinkan untuk masuk');
    }

    public function test_token_dari_f1_yang_tidak_valid_ditolak(): void
    {
        config(['e1.f1.base_url' => 'http://127.0.0.1:4100']);
        Http::fake(['*' => Http::response(['accessToken' => 'token-tidak-valid'])]);

        $this->postJson('/api/auth/login', [
            'emailAtauUsername' => 'yuril.salsabila',
            'password' => 'kata-sandi-uji',
        ])->assertUnauthorized()
            ->assertJsonPath('errorCode', 'UNAUTHORIZED');
    }

    public function test_url_f1_kosong_dilaporkan_sebagai_layanan_tidak_tersedia(): void
    {
        config(['e1.f1.base_url' => null]);

        $this->postJson('/api/auth/login', [
            'emailAtauUsername' => 'yuril.salsabila',
            'password' => 'kata-sandi-uji',
        ])->assertStatus(503)
            ->assertJsonPath('errorCode', 'F1_UNAVAILABLE');
    }

    private function issueTestToken(string $role, string $akunId): string
    {
        $now = time();

        return JWT::encode([
            'iss' => 'f1-identity',
            'aud' => 'erp-anaheim',
            'iat' => $now,
            'exp' => $now + 3600,
            'akunId' => $akunId,
            'kodePeran' => $role,
        ], (string) config('e1.f1.secret'), 'HS256');
    }
}
