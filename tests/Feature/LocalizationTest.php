<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Manager\CategoryManager;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_uses_brazilian_portuguese_and_local_timezone(): void
    {
        $this->assertSame('pt_BR', app()->getLocale());
        $this->assertSame('America/Maceio', config('app.timezone'));

        $validator = Validator::make([], ['email' => ['required', 'email']]);

        $this->assertSame('O campo e-mail é obrigatório.', $validator->errors()->first('email'));
    }

    public function test_password_reset_email_is_fully_translated(): void
    {
        $user = User::factory()->create();
        $message = (new ResetPassword('token-de-teste'))->toMail($user);

        $this->assertSame('Redefina sua senha', $message->subject);
        $this->assertSame('Redefinir senha', $message->actionText);
        $this->assertStringContainsString('solicitada uma redefinição de senha', $message->introLines[0]);
        $this->assertStringContainsString('expira em', $message->outroLines[0]);
    }

    public function test_errors_and_pagination_are_rendered_in_portuguese(): void
    {
        $this->get('/pagina-que-nao-existe')
            ->assertNotFound()
            ->assertSee('Página não encontrada')
            ->assertSee('lang="pt-BR"', false);

        $manager = User::factory()->create(['role' => UserRole::Manager]);
        foreach (range(1, 11) as $number) {
            Category::query()->create(['name' => 'Categoria '.str_pad((string) $number, 2, '0', STR_PAD_LEFT)]);
        }

        Livewire::actingAs($manager)
            ->test(CategoryManager::class)
            ->assertSee('Anterior')
            ->assertSee('Próxima');
    }
}
