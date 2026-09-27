<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\ReleaseAnnouncement;
use App\Models\User;
use App\Support\ApplicationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HelpAndVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_and_professional_can_open_the_complete_help_manual(): void
    {
        $currentVersion = app(ApplicationVersion::class)->current();
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'last_seen_version' => $currentVersion,
        ]);
        $professional = User::factory()->create([
            'role' => UserRole::Professional,
            'last_seen_version' => $currentVersion,
        ]);

        foreach ([$manager, $professional] as $user) {
            $this->actingAs($user)
                ->get(route('help'))
                ->assertOk()
                ->assertSee('Ajuda e versões')
                ->assertSee('Ordem correta para começar')
                ->assertSee('Configuração e rotina da gerência')
                ->assertSee('Rotina do profissional')
                ->assertSee('Trocas, ausências e notificações')
                ->assertSee('Status, permissões e segurança')
                ->assertSee('O que fazer quando algo não funciona')
                ->assertSee('Como instalar o Escala Fácil')
                ->assertSee('Histórico de versões')
                ->assertSee('PDF, JPG ou PNG de até 5 MB')
                ->assertSee('quatro trocas aprovadas por mês')
                ->assertSee('v'.$currentVersion)
                ->assertSee('Desenvolvido por')
                ->assertSee('KL Tecnologia');
        }

        auth()->logout();
        $this->get(route('help'))->assertRedirect(route('login'));
    }

    public function test_manual_presents_the_initial_configuration_in_dependency_order(): void
    {
        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'last_seen_version' => app(ApplicationVersion::class)->current(),
        ]);

        $this->actingAs($manager)
            ->get(route('help'))
            ->assertOk()
            ->assertSeeInOrder([
                'Cadastre as categorias',
                'Cadastre os setores',
                'Cadastre os usuários',
                'Crie os plantões',
                'Confira o calendário',
                'Inicie a operação diária',
            ])
            ->assertSeeInOrder([
                'Aguardando profissional',
                'Aguardando gerência',
                'Aprovada',
                'Rejeitada',
            ]);
    }

    public function test_release_announcement_is_shown_and_acknowledged_once_per_version(): void
    {
        $versions = app(ApplicationVersion::class);
        $user = User::factory()->create(['last_seen_version' => null]);

        Livewire::actingAs($user)
            ->test(ReleaseAnnouncement::class)
            ->assertSet('show', true)
            ->assertSee('Novidades da versão '.$versions->current())
            ->assertSee($versions->currentRelease()['title'])
            ->call('acknowledge')
            ->assertSet('show', false);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'last_seen_version' => $versions->current(),
        ]);
    }

    public function test_release_announcement_stays_hidden_after_the_current_version_is_seen(): void
    {
        $currentVersion = app(ApplicationVersion::class)->current();
        $user = User::factory()->create(['last_seen_version' => $currentVersion]);

        Livewire::actingAs($user)
            ->test(ReleaseAnnouncement::class)
            ->assertSet('show', false)
            ->assertDontSee('Novidades da versão');
    }

    public function test_version_registry_and_markdown_history_stay_in_sync(): void
    {
        $versions = app(ApplicationVersion::class);
        $history = file_get_contents(base_path('versoes.md'));

        $this->assertSame($versions->current(), $versions->currentRelease()['version']);
        $this->assertNotEmpty($versions->releases());

        foreach ($versions->releases() as $release) {
            $this->assertStringContainsString('## '.$release['version'].' —', $history);
        }
    }
}
