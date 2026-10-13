<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Data awal E-1: hanya data demo fiktif (bukan data mitra).
     * Master akun/peran milik F-1 dan invoice milik D-2 tidak diisi di sini.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
