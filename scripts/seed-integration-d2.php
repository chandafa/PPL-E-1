<?php

declare(strict_types=1);

use App\Support\Integration\SeedRepair;
use Database\Seeders\RestoredD2DemoSeeder;
use Illuminate\Contracts\Console\Kernel;

$repo = $argv[1] ?? throw new RuntimeException('Checkout D-2 diperlukan.');
require $repo.'/vendor/autoload.php';
$app = require $repo.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
require dirname(__DIR__).'/app/Support/Integration/SeedRepair.php';
require dirname(__DIR__).'/database/seeders/RestoredD2DemoSeeder.php';
SeedRepair::run($app->make('db')->connection(), function () use ($kernel): void {
    if ($kernel->call('db:seed', ['--force' => true, '--no-interaction' => true]) !== 0) {
        throw new RuntimeException('Seed D-2 gagal; transaksi dibatalkan.');
    }
    (new RestoredD2DemoSeeder)->run();
});
echo $kernel->output();
