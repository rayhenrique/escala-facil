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
                ->assertSee('Primeiros passos')
                ->assertSee('Manual da gerência')
                ->assertSee('Manual do profissional')
                ->assertSee('Regras e status')
                ->assertSee('Histórico de versões')
                ->assertSee('v'.$currentVersion)
                ->assertSee('Desenvolvido por')
                ->assertSee('KL Tecnologia');
        }

        auth()->logout();
        $this->get(route('help'))->assertRedirect(route('login'));
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
