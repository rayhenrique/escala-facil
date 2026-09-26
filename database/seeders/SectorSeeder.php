<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SectorSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        foreach (['Emergência', 'UTI'] as $name) {
            DB::table('sectors')->updateOrInsert(
                ['name' => $name],
                ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }
}
