<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['Médico Clínico', 'Enfermeiro Padrão'] as $name) {
            DB::table('categories')->updateOrInsert(
                ['name' => $name],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
