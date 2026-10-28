<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Auth\F1TokenVerifier;
use App\Support\ApiResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class F1LoginController extends Controller
{
    public function __invoke(Request $request, F1TokenVerifier $verifier): JsonResponse
    {
        $username = $request->input('emailAtauUsername');
        $password = $request->input('password');

        if (! is_string($username) || trim($username) === '' || mb_strlen($username) > 255
            || ! is_string($password) || $password === '' || mb_strlen($password) > 1024) {
            throw ApiException::badRequest('INVALID_LOGIN', 'Nama pengguna/email dan kata sandi wajib diisi');
        }

        $baseUrl = rtrim((string) config('e1.f1.base_url'), '/');
        $urlParts = parse_url($baseUrl);
        if ($baseUrl === '' || ! is_array($urlParts)
            || ! in_array($urlParts['scheme'] ?? null, ['http', 'https'], true)
            || empty($urlParts['host'])) {
            throw ApiException::make(503, 'F1_UNAVAILABLE', 'URL layanan autentikasi F-1 belum dikonfigurasi');
        }

        $loginPath = '/'.ltrim((string) config('e1.f1.login_path', '/auth/login'), '/');

        try {
            $response = Http::acceptJson()
                ->timeout((int) config('e1.f1.timeout', 5))
                ->post($baseUrl.$loginPath, [
                    'emailAtauUsername' => trim($username),
                    'password' => $password,
                ]);
        } catch (ConnectionException) {
            throw ApiException::make(502, 'F1_UNAVAILABLE', 'Layanan autentikasi F-1 tidak dapat dihubungi');
        }

        if ($response->status() === 401) {
            throw ApiException::make(401, 'UNAUTHORIZED', 'Nama pengguna/email atau kata sandi salah');
        }

        if ($response->status() === 403) {
            throw ApiException::make(403, 'FORBIDDEN', 'Akun tidak diizinkan untuk masuk');
        }

        if (! $response->successful()) {
            throw ApiException::make(502, 'F1_UNAVAILABLE', 'Layanan autentikasi F-1 gagal memproses login');
        }

        $token = $response->json('accessToken');
        if (! is_string($token) || trim($token) === '') {
            throw ApiException::make(502, 'F1_INVALID_TOKEN', 'Layanan F-1 tidak mengembalikan token akses yang valid');
        }

        $identity = $verifier->verify($token);

        return ApiResponse::ok([
            'accessToken' => $token,
            'tokenType' => 'Bearer',
            'akunId' => $identity->akunId,
            'kodePeran' => $identity->role,
        ], 'Login F-1 berhasil');
    }
}
