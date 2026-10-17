<?php

namespace Tests\Unit;

use App\Support\DateDisplay;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class DateDisplayTest extends TestCase
{
    public function test_bulan_dan_tanggal_kalender_ditampilkan_dalam_bahasa_indonesia(): void
    {
        $this->assertSame('Januari 2026', DateDisplay::period('2026-01'));
        $this->assertSame('Oktober 2026', DateDisplay::period('2026-10'));
        $this->assertSame('10 Januari 2026', DateDisplay::date(CarbonImmutable::parse('2026-01-10', 'UTC')));
    }

    public function test_konversi_wib_melewati_pergantian_tahun_tanpa_mengubah_sumber(): void
    {
        $time = CarbonImmutable::parse('2025-12-31T17:30:00Z');
        $this->assertSame('1 Januari 2026, 00.30 WIB', DateDisplay::dateTime($time));
        $this->assertSame('2025-12-31T17:30:00+00:00', $time->toIso8601String());
    }
}
