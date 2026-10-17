<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

final class DateDisplay
{
    public static function period(string $period): string
    {
        return CarbonImmutable::createFromFormat('!Y-m', $period, 'Asia/Jakarta')->locale('id')->translatedFormat('F Y');
    }

    public static function date(CarbonInterface $date): string
    {
        return $date->copy()->locale('id')->translatedFormat('j F Y');
    }

    public static function dateTime(CarbonInterface $time): string
    {
        $local = $time->copy()->setTimezone('Asia/Jakarta');

        return self::date($local).', '.$local->format('H.i').' WIB';
    }
}
