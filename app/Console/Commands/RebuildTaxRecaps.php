<?php

namespace App\Console\Commands;

use App\Services\Tax\RecapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildTaxRecaps extends Command
{
    protected $signature = 'e1:recaps:rebuild';

    protected $description = 'Hitung ulang seluruh masa pajak dengan aturan faktur aktif UI v0.2';

    public function handle(RecapService $service): int
    {
        $periods = DB::table('faktur_pajak')->select('tax_period')
            ->union(DB::table('pembayaran_pajak')->select('tax_period'))
            ->union(DB::table('rekap_pajak_bulanan')->select('tax_period'))
            ->orderBy('tax_period')->pluck('tax_period');

        DB::transaction(function () use ($periods, $service): void {
            foreach ($periods as $period) {
                $service->assertPeriod($period);
                $service->refresh($period);
            }
        });

        $this->info($periods->count().' masa pajak dihitung ulang.');

        return self::SUCCESS;
    }
}
