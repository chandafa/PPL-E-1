<?php

namespace App\Support\Integration;

use App\Services\Auth\F1ApiClient;
use RuntimeException;

class DemoIdentity
{
    public function __construct(private readonly F1ApiClient $accounts) {}

    public function authenticate(): array
    {
        if (! app()->environment(['local', 'testing'])
            || config('e1.f1.base_url') !== 'http://127.0.0.1:4100'
            || config('e1.d2.base_url') !== 'http://127.0.0.1:8026') {
            throw new RuntimeException('Seed faktur HTTP hanya untuk runtime integrasi lokal F-1/D-2.');
        }
        Runtime::ready(base_path(), 'http');
        $identity = $this->accounts->login((string) config('e1.demo.f1_username'), (string) config('e1.demo.f1_password'));
        $roles = array_map('strtolower', (array) config('e1.roles.allowed'));
        if (! in_array(strtolower($identity['roleCode']), $roles, true)) {
            throw new RuntimeException('Akun F-1 untuk seed faktur tidak memiliki hak pencatatan pajak.');
        }

        return $identity;
    }
}
