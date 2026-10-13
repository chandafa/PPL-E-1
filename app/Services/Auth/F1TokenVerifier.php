<?php

namespace App\Services\Auth;

use App\Exceptions\ApiException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

/**
 * Memverifikasi token Bearer F-1 (JWT): tanda tangan, exp, iss, dan aud
 * (Slide 2: "Validasi token"). Algoritma dibatasi satu nilai dari konfigurasi
 * agar tidak rentan pada pertukaran algoritma.
 */
class F1TokenVerifier
{
    public function __construct(private readonly array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('e1.f1'));
    }

    /**
     * @throws ApiException 401 UNAUTHORIZED bila token tidak ada/tidak sah
     */
    public function verify(string $token): F1Identity
    {
        try {
            JWT::$leeway = (int) ($this->config['leeway'] ?? 30);
            $claims = (array) JWT::decode($token, $this->key());
        } catch (Throwable) {
            throw $this->unauthorized();
        }

        if (! isset($claims['exp'])) {
            throw $this->unauthorized();
        }

        $issuer = $this->config['issuer'] ?? null;
        if ($issuer !== null && $issuer !== '' && ($claims['iss'] ?? null) !== $issuer) {
            throw $this->unauthorized();
        }

        $audience = $this->config['audience'] ?? null;
        if ($audience !== null && $audience !== '') {
            $tokenAudience = (array) ($claims['aud'] ?? []);
            if (! in_array($audience, $tokenAudience, true)) {
                throw $this->unauthorized();
            }
        }

        $akunId = $this->claim($claims, [$this->config['claim_akun_id'] ?? 'akunId', 'sub']);
        $role = $this->claim($claims, [$this->config['claim_role'] ?? 'kodePeran', 'role', 'peran']);

        if ($akunId === null) {
            throw $this->unauthorized();
        }

        // Peran tidak ada dianggap tidak berwenang (403 dari middleware), bukan token palsu.
        return new F1Identity($akunId, $role ?? '');
    }

    private function key(): Key
    {
        $algo = (string) ($this->config['algo'] ?? 'HS256');

        if (str_starts_with($algo, 'HS')) {
            return new Key((string) ($this->config['secret'] ?? ''), $algo);
        }

        $path = (string) ($this->config['public_key_path'] ?? '');
        $material = $path !== '' && is_file($path) ? (string) file_get_contents($path) : '';

        return new Key($material, $algo);
    }

    private function claim(array $claims, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($claims[$name]) && is_string($claims[$name]) && $claims[$name] !== '') {
                return $claims[$name];
            }
        }

        return null;
    }

    private function unauthorized(): ApiException
    {
        return ApiException::make(401, 'UNAUTHORIZED', 'Token tidak ada atau tidak sah');
    }
}
