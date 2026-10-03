<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_visit_landing_page_at_root(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Escala Fácil');
        $response->assertSeeInOrder(['O seu plantão,', 'sob o seu controle.']);
        $response->assertSee('Esqueça os grupos de WhatsApp e as planilhas confusas.');
        $response->assertSee('Troque o caos pela precisão.');
        $response->assertSee('Tríade de Superpoderes');
        $response->assertSee('Troca em 1 Clique');
        $response->assertSee('Calendário no Bolso');
        $response->assertSee('Transparência Total');
        $response->assertSee('Aprovação em segundos. Veja na prática.');
        $response->assertSee('Sua escala está pronta para ser visualizada.');
        $response->assertSee('Acessar Plataforma');
        $response->assertSee(route('login'));
    }

    public function test_guest_can_visit_landing_page_at_named_route(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Escala Fácil');
        $response->assertSee('PWA Nativo');
        $response->assertSee('Acesso Exclusivo Institucional');
    }

    public function test_authenticated_user_visiting_root_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Professional]);

        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect(route('dashboard'));
    }

    public function test_landing_page_contains_pwa_and_meta_tags(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('<meta name="theme-color" content="#059669">', false);
        $response->assertSee('<body id="landing-page"', false);
    }
}
