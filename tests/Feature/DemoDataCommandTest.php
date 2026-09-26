<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\ShiftSwapEventNotification;
use App\Services\DemoDataService;
use DomainException;
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

        $demoUser = User::query()->where('email', 'like', 'demo.%')->firstOrFail();
        $demoUser->notify(new ShiftSwapEventNotification(['event' => 'demo']));
        $manager->notify(new ShiftSwapEventNotification(['event' => 'real']));
        $demoNotificationId = $demoUser->notifications()->sole()->id;
        $realNotificationId = $manager->notifications()->sole()->id;

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
        $this->assertDatabaseMissing('notifications', ['id' => $demoNotificationId]);
        $this->assertDatabaseHas('notifications', ['id' => $realNotificationId]);
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

    public function test_demo_service_requires_explicit_authorization_in_production(): void
    {
        $originalEnvironment = app()->environment();
        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->assertThrows(
                fn () => app(DemoDataService::class)->seed(),
                DomainException::class,
            );
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }

        $this->assertDatabaseCount('demo_data_batches', 0);
    }
}
