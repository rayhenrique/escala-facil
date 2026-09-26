<?php

namespace Database\Seeders;

use App\Services\DemoDataService;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(DemoDataService $demoData): void
    {
        $demoData->seed();
    }
}
