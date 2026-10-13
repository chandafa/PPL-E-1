<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use Firebase\JWT\JWT;

/**
 * Menerbitkan token F-1 PALSU (HS256) untuk pengembangan lokal dan demo.
 * Token memakai rahasia, iss, aud, dan nama klaim yang sama dengan verifikator,
 * sehingga diterima API seperti token F-1 sungguhan. Hanya untuk non-production.
 */
class DevTokenIssuer
{
    public const MIN_TTL = 60;

    public const MAX_TTL = 86400;

    /**
     * @return array{token: string, expiresIn: int, expiresAt: int}
     */
    public function issue(string $akunId, string $role, int $ttl = 3600): array
    {
        $config = (array) config('e1.f1');
        $algo = (string) ($config['algo'] ?? 'HS256');

        if (! str_starts_with($algo, 'HS')) {
            throw ApiException::make(400, 'DEV_LOGIN_UNSUPPORTED', 'Penerbitan token lokal hanya mendukung HS256/HS384/HS512');
        }

        $now = time();
        $claims = [
            'iss' => $config['issuer'] ?: 'f1-identity',
            'aud' => $config['audience'] ?: 'erp-anaheim',
            'iat' => $now,
            'exp' => $now + $ttl,
            ($config['claim_akun_id'] ?? 'akunId') => $akunId,
            ($config['claim_role'] ?? 'kodePeran') => $role,
        ];

        return [
            'token' => JWT::encode($claims, (string) $config['secret'], $algo),
            'expiresIn' => $ttl,
            'expiresAt' => $now + $ttl,
        ];
    }
}
