<?php

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\Auth\F1ApiClient;
use App\Services\Auth\F1Identity;
use App\Services\Auth\F1TokenVerifier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu autentikasi dan otorisasi API E-1.
 *   - tanpa token / token tidak sah  -> 401 UNAUTHORIZED
 *   - token sah, peran tidak berwenang -> 403 FORBIDDEN
 *
 * Parameter "summary" menambahkan peran layanan konsumen (E1_SUMMARY_EXTRA_ROLES)
 * untuk API-KE01-10.
 */
class AuthenticateF1Token
{
    public const ATTRIBUTE = 'f1.identity';

    public function __construct(private readonly F1TokenVerifier $verifier, private readonly F1ApiClient $accounts) {}

    public function handle(Request $request, Closure $next, string $ability = 'manage'): Response
    {
        $header = (string) $request->header('Authorization', '');

        if (preg_match('/^Bearer\s+(\S+)$/i', trim($header), $matches) !== 1) {
            throw ApiException::make(401, 'UNAUTHORIZED', 'Token tidak ada atau tidak sah');
        }

        $identity = $this->verifier->verify($matches[1]);
        if (config('e1.f1.check_account')) {
            $identity = $this->accounts->currentIdentity($identity, $matches[1]);
        }

        if (! $this->isAuthorized($identity, $ability)) {
            throw ApiException::make(403, 'FORBIDDEN', 'Pengguna sah, akses tidak cukup');
        }

        $request->attributes->set(self::ATTRIBUTE, $identity);

        return $next($request);
    }

    private function isAuthorized(F1Identity $identity, string $ability): bool
    {
        $roles = (array) config('e1.roles.allowed', []);
        if ($ability === 'summary') {
            $roles = array_merge($roles, (array) config('e1.roles.summary_extra', []));
        }

        $normalized = array_map('mb_strtolower', $roles);

        return in_array(mb_strtolower($identity->role), $normalized, true);
    }
}
