<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('O seeder padrão contém credenciais de desenvolvimento e não pode ser executado em produção.');
        }

        $this->call([
            CategorySeeder::class,
            SectorSeeder::class,
            UserSeeder::class,
        ]);
    }
}
