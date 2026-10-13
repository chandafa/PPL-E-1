<?php

namespace App\Services\Auth;

/**
 * Identitas pemanggil yang diambil dari token F-1 yang sudah diverifikasi.
 */
final class F1Identity
{
    public function __construct(
        public readonly string $akunId,
        public readonly string $role,
    ) {}
}
