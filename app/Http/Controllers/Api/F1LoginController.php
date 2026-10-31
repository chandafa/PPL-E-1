<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Auth\F1ApiClient;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class F1LoginController extends Controller
{
    public function __invoke(Request $request, F1ApiClient $client): JsonResponse
    {
        if (! config('e1.f1.api_enabled')) {
            throw ApiException::notFound('NOT_FOUND', 'Sumber daya tidak ditemukan');
        }
        $username = $request->input('emailOrUsername');
        $password = $request->input('password');
        if (! is_string($username) || trim($username) === '' || mb_strlen($username) > 254
            || ! is_string($password) || $password === '' || mb_strlen($password) > 1024) {
            throw ApiException::badRequest('INVALID_LOGIN', 'Email/username dan kata sandi wajib diisi dengan benar');
        }

        return ApiResponse::ok($client->login(trim($username), $password), 'Login melalui F-1 berhasil')
            ->header('Cache-Control', 'no-store');
    }
}
