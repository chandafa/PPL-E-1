<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Aritmetika nominal decimal(18,2) sebagai string (bcmath); tidak pernah float.
 */
final class Money
{
    private const PATTERN = '/^[0-9]{1,16}(\.[0-9]{1,2})?$/';

    public static function isValid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::PATTERN, $value) === 1;
    }

    /** Normalkan "100" / "100.5" menjadi "100.00"; lempar bila bukan nominal valid. */
    public static function normalize(string $value): string
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException("Nominal tidak valid: {$value}");
        }

        return bcadd($value, '0', 2);
    }

    public static function add(string $a, string $b): string
    {
        return bcadd($a, $b, 2);
    }

    public static function sub(string $a, string $b): string
    {
        $result = bcsub($a, $b, 2);

        // bcsub dapat menghasilkan "-0.00" pada beberapa versi; samakan menjadi "0.00".
        return $result === '-0.00' ? '0.00' : $result;
    }

    public static function sum(iterable $values): string
    {
        $total = '0.00';
        foreach ($values as $value) {
            $total = self::add($total, (string) $value);
        }

        return $total;
    }

    public static function equals(string $a, string $b): bool
    {
        return bccomp($a, $b, 2) === 0;
    }
}
