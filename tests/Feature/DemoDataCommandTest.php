<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DemoDataCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_can_be_installed_and_removed_without_deleting_existing_records(): void
    {
        $manager = User::factory()->create([
            'email' => 'gerente.real@example.test',
            'role' => UserRole::Manager,
        ]);
        $existingCategory = Category::query()->create(['name' => 'Categoria real']);
        $existingSector = Sector::query()->create(['name' => 'Setor real']);

        $this->artisan('demo:data', ['action' => 'seed'])
            ->expectsOutput('Dados de demonstração instalados com sucesso.')
            ->assertSuccessful();

        $this->assertDatabaseHas('demo_data_batches', ['key' => DemoDataService::BATCH_KEY]);
        $this->assertDatabaseCount('categories', 6);
        $this->assertDatabaseCount('sectors', 6);
        $this->assertDatabaseCount('users', 21);
        $this->assertSame(102, Shift::query()->count());
        $this->assertSame(12, ShiftSwap::query()->count());
        $this->assertGreaterThan(0, DB::table('absences')->count());

        $this->artisan('demo:data', ['action' => 'clear'])
            ->expectsOutput('Dados de demonstração removidos com sucesso.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('demo_data_batches', ['key' => DemoDataService::BATCH_KEY]);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('sectors', 1);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('shifts', 0);
        $this->assertDatabaseCount('shift_swaps', 0);
        $this->assertDatabaseCount('absences', 0);
        $this->assertDatabaseHas('users', ['id' => $manager->id]);
        $this->assertDatabaseHas('categories', ['id' => $existingCategory->id]);
        $this->assertDatabaseHas('sectors', ['id' => $existingSector->id]);
    }

    public function test_demo_seed_refuses_a_duplicate_batch_and_refresh_recreates_it(): void
    {
        User::factory()->create(['role' => UserRole::Manager]);

        $this->artisan('demo:data seed')->assertSuccessful();
        $this->artisan('demo:data seed')->assertFailed();
        $this->artisan('demo:data refresh')->assertSuccessful();

        $this->assertDatabaseHas('demo_data_batches', ['key' => DemoDataService::BATCH_KEY]);
        $this->assertSame(20, User::query()->where('email', 'like', 'demo.%')->count());
        $this->assertSame(102, Shift::query()->count());
    }
}
