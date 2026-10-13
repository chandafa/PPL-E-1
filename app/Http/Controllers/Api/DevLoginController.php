<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Services\Auth\DevTokenIssuer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Login TIRUAN F-1 untuk Postman dan demo lokal (di luar kontrak openapi.yaml).
 * Login sebenarnya dimiliki F-1; endpoint ini hanya aktif bila E1_DEV_LOGIN=true
 * dan bukan production, selain itu berperilaku seperti rute yang tidak ada (404).
 */
class DevLoginController extends Controller
{
    public function __invoke(Request $request, DevTokenIssuer $issuer): JsonResponse
    {
        if (! config('e1.dev_login.enabled') || app()->isProduction()) {
            throw ApiException::notFound('NOT_FOUND', 'Sumber daya tidak ditemukan');
        }

        $akunId = $request->input('akunId');
        $role = $request->input('kodePeran');
        $ttl = $request->input('ttl', 3600);

        if (! is_string($akunId) || trim($akunId) === '' || mb_strlen($akunId) > 64) {
            throw ApiException::badRequest('INVALID_LOGIN', 'akunId wajib diisi (contoh AKN-0002)');
        }

        if (! is_string($role) || trim($role) === '' || mb_strlen($role) > 32) {
            throw ApiException::badRequest('INVALID_LOGIN', 'kodePeran wajib diisi (contoh Admin, CEO, COO)');
        }

        if (! is_numeric($ttl) || (int) $ttl < DevTokenIssuer::MIN_TTL || (int) $ttl > DevTokenIssuer::MAX_TTL) {
            throw ApiException::badRequest('INVALID_LOGIN', 'ttl harus 60 sampai 86400 detik');
        }

        $issued = $issuer->issue(trim($akunId), trim($role), (int) $ttl);

        return ApiResponse::ok([
            'accessToken' => $issued['token'],
            'tokenType' => 'Bearer',
            'expiresIn' => $issued['expiresIn'],
            'akunId' => trim($akunId),
            'kodePeran' => trim($role),
        ], 'Token pengembangan (F-1 tiruan, hanya untuk lokal)');
    }
}
