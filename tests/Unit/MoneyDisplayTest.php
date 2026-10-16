<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyDisplayTest extends TestCase
{
    public function test_format_indonesia_tetap_presisi_untuk_nol_negatif_dan_nilai_besar(): void
    {
        $this->assertSame('0,00', Money::display('0.00'));
        $this->assertSame('11.000.000,00', Money::display('11000000.00'));
        $this->assertSame('1.500,50', Money::display('1500.50'));
        $this->assertSame('-600.000,00', Money::display('-600000.00'));
        $this->assertSame('9.999.999.999.999.999,99', Money::display('9999999999999999.99'));
        $this->assertNull(Money::display(null));
    }
}
