<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateF1Token;
use App\Services\Auth\F1ApiClient;
use App\Services\Auth\F1Identity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends Controller
{
    public function __invoke(Request $request, F1ApiClient $accounts): JsonResponse
    {
        /** @var F1Identity $identity */
        $identity = $request->attributes->get(AuthenticateF1Token::ATTRIBUTE);

        return ApiResponse::ok([
            'name' => $accounts->displayName($identity, (string) $request->bearerToken()),
            'role' => $identity->role,
        ], 'Profil pengguna')->header('Cache-Control', 'no-store');
    }
}
