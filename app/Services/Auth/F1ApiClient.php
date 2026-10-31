<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Konsumen F-1 v0.4: token diterbitkan F-1, E-1 tidak menyimpan password. */
class F1ApiClient
{
    public function __construct(private readonly F1TokenVerifier $verifier) {}

    /** @return array{accessToken: string, tokenType: string, expiresIn: int, accountId: string, roleCode: string} */
    public function login(string $username, string $password): array
    {
        try {
            $response = $this->client()->post($this->url('login_path'), [
                'emailOrUsername' => $username,
                'password' => $password,
            ]);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if ($response->status() === 401) {
            throw ApiException::make(401, 'INVALID_CREDENTIALS', 'Email/username atau kata sandi salah');
        }
        if ($response->status() === 403) {
            throw ApiException::make(403, 'F1_ACCOUNT_BLOCKED', 'Akun F-1 terkunci atau nonaktif');
        }
        if (! $response->successful()) {
            throw $this->unavailable();
        }

        $data = $response->json();
        if (! is_array($data)
            || ! is_string($data['accessToken'] ?? null)
            || ($data['tokenType'] ?? null) !== 'Bearer'
            || ! is_int($data['expiresIn'] ?? null) || $data['expiresIn'] <= 0
            || ! is_string($data['accountId'] ?? null) || $data['accountId'] === ''
            || ! is_string($data['roleCode'] ?? null) || $data['roleCode'] === '') {
            throw $this->invalidResponse();
        }

        try {
            $identity = $this->verifier->verify($data['accessToken']);
        } catch (ApiException) {
            throw ApiException::make(502, 'F1_INVALID_TOKEN', 'Token dari F-1 tidak lolos verifikasi E-1');
        }
        if ($identity->akunId !== $data['accountId'] || $identity->role !== $data['roleCode']) {
            throw $this->invalidResponse();
        }
        if (config('e1.f1.check_account')) {
            $identity = $this->currentIdentity($identity, $data['accessToken']);
        }

        return [
            'accessToken' => $data['accessToken'],
            'tokenType' => 'Bearer',
            'expiresIn' => $data['expiresIn'],
            'accountId' => $identity->akunId,
            'roleCode' => $identity->role,
        ];
    }

    /** Peran/status terkini dari F-1; token lama tidak mempertahankan akses yang telah dicabut. */
    public function currentIdentity(F1Identity $identity, string $token): F1Identity
    {
        $path = str_replace('{accountId}', rawurlencode($identity->akunId), (string) config('e1.f1.role_path'));
        try {
            $response = $this->client()->withToken($token)->get($this->baseUrl().$path);
        } catch (ConnectionException) {
            throw $this->unavailable();
        }

        if (in_array($response->status(), [401, 404], true)) {
            throw ApiException::make(401, 'UNAUTHORIZED', 'Identitas akun F-1 tidak sah');
        }
        if ($response->status() === 403) {
            throw ApiException::make(403, 'FORBIDDEN', 'Akses akun F-1 ditolak');
        }
        if (! $response->successful()) {
            throw $this->unavailable();
        }

        $data = $response->json();
        if (! is_array($data) || ($data['accountId'] ?? null) !== $identity->akunId
            || ! is_string($data['roleCode'] ?? null) || $data['roleCode'] === ''
            || ! in_array($data['status'] ?? null, ['active', 'inactive', 'locked'], true)) {
            throw $this->invalidResponse();
        }
        if ($data['status'] !== 'active') {
            throw ApiException::make(403, 'FORBIDDEN', 'Akun F-1 terkunci atau nonaktif');
        }

        return new F1Identity($data['accountId'], $data['roleCode']);
    }

    private function client(): PendingRequest
    {
        return Http::acceptJson()->asJson()->withoutRedirecting()->timeout((int) config('e1.f1.timeout', 5))
            ->withHeaders(['X-Request-ID' => $this->requestId()]);
    }

    private function requestId(): string
    {
        $value = (string) request()->header('X-Request-ID', '');

        return preg_match('/^[A-Za-z0-9._:-]{1,100}$/D', $value) === 1 ? $value : (string) Str::uuid7();
    }

    private function baseUrl(): string
    {
        if (! config('e1.f1.api_enabled') || trim((string) config('e1.f1.base_url')) === '') {
            throw $this->unavailable();
        }

        return rtrim((string) config('e1.f1.base_url'), '/');
    }

    private function url(string $setting): string
    {
        return $this->baseUrl().(string) config('e1.f1.'.$setting);
    }

    private function unavailable(): ApiException
    {
        return ApiException::make(503, 'F1_UNAVAILABLE', 'Layanan F-1 tidak tersedia; coba kembali setelah layanan pulih');
    }

    private function invalidResponse(): ApiException
    {
        return ApiException::make(502, 'F1_INVALID_RESPONSE', 'Respons identitas F-1 tidak sesuai kontrak');
    }
}
