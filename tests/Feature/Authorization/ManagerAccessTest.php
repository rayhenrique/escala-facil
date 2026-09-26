<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_sent_to_login(): void
    {
        $this->get('/manager/dashboard')->assertRedirect(route('login'));
    }

    public function test_professional_receives_forbidden_response(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)
            ->get('/manager/dashboard')
            ->assertForbidden()
            ->assertSee('Você não tem acesso a esta área.');
    }

    public function test_manager_can_open_manager_routes(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)
            ->get('/manager/dashboard')
            ->assertOk();
    }
}
