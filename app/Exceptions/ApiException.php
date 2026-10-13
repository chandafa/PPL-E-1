<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kesalahan terstruktur API E-1: status HTTP + errorCode (enum pada openapi.yaml)
 * + pesan yang dapat dibaca. Dirender menjadi amplop {code, message, data:null, errorCode}.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(int $status, string $errorCode, string $message): self
    {
        return new self($status, $errorCode, $message);
    }

    public static function badRequest(string $errorCode, string $message): self
    {
        return new self(400, $errorCode, $message);
    }

    public static function notFound(string $errorCode, string $message): self
    {
        return new self(404, $errorCode, $message);
    }

    public static function conflict(string $errorCode, string $message): self
    {
        return new self(409, $errorCode, $message);
    }
}
