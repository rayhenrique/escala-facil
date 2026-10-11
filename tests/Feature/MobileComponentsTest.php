<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Manager\SectorManager;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class MobileComponentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_responsive_sector_form_preserves_validation_save_cancel_and_authorization(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $screen = Livewire::actingAs($manager)->test(SectorManager::class)->call('openCreateModal')
            ->assertSet('showFormModal', true)->assertSeeHtml('role="dialog"')
            ->assertSeeHtml('aria-modal="true"')->assertSeeHtml('aria-labelledby="sector-form-title"')
            ->assertSeeHtml('aria-describedby="sector-form-description"')->assertSeeHtml('wire:submit="save"')
            ->assertSeeHtml('data-ui-dialog-component=')->assertSee('Descartar alterações');
        $screen->call('save')->assertHasErrors('name')->assertSet('showFormModal', true)
            ->assertSeeHtml('aria-invalid="true"');
        $screen->set('name', 'UTI nova')->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
        $sector = Sector::where('name', 'UTI nova')->sole();
        $screen->call('edit', $sector->id)->set('name', 'Não persistir')->call('closeFormModal');
        $this->assertSame('UTI nova', $sector->fresh()->name);
        $screen->call('requestDelete', $sector->id)->assertSeeHtml('role="alertdialog"')
            ->assertSeeHtml('aria-labelledby="delete-sector-title"')->call('cancelDelete');
        $this->assertDatabaseHas('sectors', ['id' => $sector->id]);
        Livewire::actingAs(User::factory()->create())->test(SectorManager::class)->assertForbidden();
    }

    public function test_reusable_primitives_escape_content_and_have_accessible_labels(): void
    {
        $html = Blade::render('<x-action-button icon="close" label="Fechar" target="save">Fechar</x-action-button>');
        $this->assertStringContainsString('aria-label="Fechar"', $html);
        $this->assertStringContainsString('wire:loading.attr="disabled"', $html);
        $this->assertStringContainsString('role="status"', $html);
        $empty = Blade::render('<x-empty-state :title="$title" description="Altere a busca." />', ['title' => '<script>alert(1)</script>']);
        $this->assertStringNotContainsString('<script>', $empty);
        $this->assertStringContainsString('&lt;script&gt;', $empty);
        $progress = Blade::render('<x-step-progress :steps="$steps" :current="2" />', ['steps' => ['Selecionar', 'Conferir', 'Confirmar']]);
        $this->assertSame(1, substr_count($progress, 'aria-current="step"'));
        $this->assertStringContainsString('Etapa 2 de 3:', $progress);
        $this->assertStringContainsString('Concluída', $progress);
        $toast = Blade::render('<x-toast :message="$message" tone="error" />', ['message' => '<b>Erro</b>']);
        $this->assertStringContainsString('role="alert"', $toast);
        $this->assertStringContainsString('duration: 0', $toast);
        $this->assertStringContainsString('&lt;b&gt;Erro&lt;/b&gt;', $toast);
        $this->assertStringContainsString('role="status"', Blade::render('<x-loading-state />'));
    }
}
