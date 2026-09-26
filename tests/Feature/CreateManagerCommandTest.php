<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateManagerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_be_created_with_an_interactively_collected_password(): void
    {
        $this->artisan('user:create-manager', [
            '--name' => 'Gerente Principal',
            '--email' => 'gerente.principal@example.test',
        ])
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'SenhaSegura@123')
            ->expectsQuestion('Confirme a senha', 'SenhaSegura@123')
            ->expectsOutput('Gerente criado com sucesso: gerente.principal@example.test')
            ->assertSuccessful();

        $manager = User::query()->where('email', 'gerente.principal@example.test')->sole();

        $this->assertSame(UserRole::Manager, $manager->role);
        $this->assertNull($manager->category_id);
        $this->assertNull($manager->sector_id);
        $this->assertTrue(Hash::check('SenhaSegura@123', $manager->password));
    }

    public function test_duplicate_email_is_rejected_without_changing_the_existing_user(): void
    {
        $existing = User::factory()->create(['email' => 'existente@example.test']);

        $this->artisan('user:create-manager', [
            '--name' => 'Outro gerente',
            '--email' => 'EXISTENTE@example.test',
        ])
            ->expectsQuestion('Senha (mínimo de 12 caracteres)', 'SenhaSegura@123')
            ->expectsQuestion('Confirme a senha', 'SenhaSegura@123')
            ->assertExitCode(Command::INVALID);

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($existing->role, $existing->refresh()->role);
    }
}
