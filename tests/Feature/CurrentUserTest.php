<?php

namespace Tests\Feature;

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Http;

class CurrentUserTest extends TestCase
{
    private const ACCOUNT = '01929b4a-6e2a-7b3f-8c71-0ab4e3d9f2a1';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'e1.f1.api_enabled' => true, 'e1.f1.base_url' => 'http://f1.test',
            'e1.f1.check_account' => true,
        ]);
        Http::preventStrayRequests();
    }

    private function headers(): array
    {
        $token = JWT::encode([
            'iss' => config('e1.f1.issuer'), 'aud' => config('e1.f1.audience'),
            'iat' => time(), 'exp' => time() + 300,
            config('e1.f1.claim_akun_id') => self::ACCOUNT,
            config('e1.f1.claim_role') => 'Admin',
        ], config('e1.f1.secret'), 'HS256');

        return ['Authorization' => 'Bearer '.$token];
    }

    private function responses(mixed $profile): void
    {
        Http::fake([
            'http://f1.test/accounts/*/role' => Http::response([
                'accountId' => self::ACCOUNT, 'roleCode' => 'Admin', 'status' => 'active',
            ]),
            'http://f1.test/identity/accounts/*' => $profile,
        ]);
    }

    public function test_sidebar_profile_returns_f1_name_and_verified_role(): void
    {
        $this->responses(Http::response([
            'accountId' => self::ACCOUNT, 'name' => 'Super Admin', 'roleCode' => 'CEO',
        ]));

        $this->getJson('/api/ui/profile', $this->headers())->assertOk()
            ->assertJsonPath('data.name', 'Super Admin')
            ->assertJsonPath('data.role', 'Admin')->assertDontSee(self::ACCOUNT);
        Http::assertSent(fn ($request) => $request->url() === 'http://f1.test/identity/accounts/'.self::ACCOUNT
            && $request->hasHeader('Authorization'));
    }

    public function test_other_account_profile_is_not_displayed(): void
    {
        $this->responses(Http::response(['accountId' => 'other-account', 'name' => 'Other Person']));

        $this->getJson('/api/ui/profile', $this->headers())->assertOk()
            ->assertJsonPath('data.name', 'Pengguna F-1')->assertDontSee('Other Person')->assertDontSee(self::ACCOUNT);
    }

    public function test_profile_outage_does_not_prevent_access_or_display_uuid(): void
    {
        $this->responses(Http::failedConnection());

        $this->getJson('/api/ui/profile', $this->headers())->assertOk()
            ->assertJsonPath('data.name', 'Pengguna F-1')->assertDontSee(self::ACCOUNT);
    }

    public function test_standalone_mode_does_not_call_f1(): void
    {
        config(['e1.f1.api_enabled' => false, 'e1.f1.check_account' => false]);
        Http::fake();

        $this->getJson('/api/ui/profile', $this->headers())->assertOk()
            ->assertJsonPath('data.name', 'Pengguna F-1');
        Http::assertNothingSent();
    }

    public function test_profile_requires_authentication(): void
    {
        Http::fake();

        $this->getJson('/api/ui/profile')->assertUnauthorized();
        Http::assertNothingSent();
    }
}
