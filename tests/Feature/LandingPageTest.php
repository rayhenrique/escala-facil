<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\View\Components\Landing\ProductPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $response->assertSee('Escalas, trocas e cobertura em um só lugar.');
        $response->assertSee('Mais contexto para decidir.');
        $response->assertSee('Recursos do aplicativo');
        $response->assertSee('Solicitar e acompanhar');
        $response->assertSee('Consultar a agenda');
        $response->assertSee('Conferir e registrar');
        $response->assertSee('Um pedido, três etapas claras.');
        $response->assertSee('Sua rotina de plantões,');
        $response->assertSee('Acessar plataforma');
        $response->assertSee(route('login'));
    }

    public function test_guest_can_visit_landing_page_at_named_route(): void
    {
        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Escala Fácil');
        $response->assertSee('Prévia · dados ilustrativos');
        $response->assertSee('Acesso institucional');
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
        $response->assertSee('<meta name="theme-color" content="#0A4D59">', false);
        $response->assertSee('<body id="landing-page"', false);
    }

    public function test_landing_page_footer_contains_author_link_and_dynamic_version(): void
    {
        $currentVersion = config('version.current');

        $response = $this->get(route('landing'));

        $response->assertOk();
        $response->assertSee('Desenvolvido por');
        $response->assertSee('https://kltecnologia.com', false);
        $response->assertSee('KL Tecnologia');
        $response->assertSee('Versão '.$currentVersion);
    }

    public function test_preview_uses_real_shift_representation_without_reading_or_exposing_private_records(): void
    {
        User::factory()->create(['name' => 'Nome privado não publicável']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $preview = new ProductPreview;
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertFalse($preview->shift->exists);
        $this->get('/landing')->assertOk()->assertSee($preview->shift->time_label)
            ->assertSee('15/11/2026')->assertSee('dia seguinte')->assertSee('shift-card-scheduled', false)
            ->assertDontSee('Nome privado não publicável');
    }

    public function test_public_copy_explains_institutional_decisions_without_unverified_promises(): void
    {
        $response = $this->get('/landing')->assertOk();
        $response->assertSee('um pedido também pode ser rejeitado')
            ->assertSee('O aceite ainda não confirma a troca.')
            ->assertSee('Consulte a gerência da sua instituição.');
        foreach (['em segundos', '30 seg', '100%', 'garantida', 'segurança jurídica', 'hash de segurança', 'Zero mensagens', 'Notificações automáticas no aparelho'] as $claim) {
            $response->assertDontSee($claim);
        }
    }
}
