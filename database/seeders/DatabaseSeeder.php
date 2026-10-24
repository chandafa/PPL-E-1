<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the current D-2 reference snapshot into the shared E-1 database.
     */
    public function run(): void
    {
        $this->call(ModulD2Seeder::class);
    }
}
