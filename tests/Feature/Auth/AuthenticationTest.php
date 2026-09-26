<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_is_rendered_and_public_registration_is_disabled(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Entre na sua conta')
            ->assertSee('method="post"', false)
            ->assertSee('window.livewireScriptConfig', false)
            ->assertDontSee('livewire.min.js', false);

        $this->get('/register')->assertNotFound();
    }

    public function test_manager_can_authenticate_and_is_redirected_to_manager_dashboard(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::test(Login::class)
            ->set('form.email', $manager->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect('/dashboard');

        $this->assertAuthenticatedAs($manager);
        $this->get('/dashboard')->assertRedirect(route('manager.dashboard'));
        $this->get(route('manager.dashboard'))->assertOk()->assertSee('Visão da gerência');
    }

    public function test_professional_is_redirected_to_professional_dashboard(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)
            ->get('/dashboard')
            ->assertRedirect(route('professional.dashboard'));
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        Livewire::test(Login::class)
            ->set('form.email', $user->email)
            ->set('form.password', 'senha-incorreta')
            ->call('login')
            ->assertHasErrors(['form.email']);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
