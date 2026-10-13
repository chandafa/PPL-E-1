<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Amplop respons bersama (aturan dosen):
 *   sukses : {"code":"200","message":"...","data":{...}}
 *   gagal  : {"code":"4xx","message":"...","data":null,"errorCode":"ENUM"}
 * "code" selalu sama dengan status HTTP, ditulis sebagai string.
 */
final class ApiResponse
{
    public static function ok(mixed $data, string $message): JsonResponse
    {
        return response()->json([
            'code' => '200',
            'message' => $message,
            'data' => $data,
        ], 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    public static function error(int $status, string $errorCode, string $message): JsonResponse
    {
        return response()->json([
            'code' => (string) $status,
            'message' => $message,
            'data' => null,
            'errorCode' => $errorCode,
        ], $status, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
