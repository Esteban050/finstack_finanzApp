<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with normative parameters, default categories, and initial admin.
     */
    public function run(): void
    {
        $this->call([
            ParametroNormativoSeeder::class,
            CategoriaPredeterminadaSeeder::class,
        ]);
    }
}
