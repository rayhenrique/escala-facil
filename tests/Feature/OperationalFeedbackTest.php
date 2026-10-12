<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Manager\SectorManager;
use App\Models\Sector;
use App\Models\User;
use App\Support\UiFeedback;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_confirmed_operations_have_distinct_feedback_identity_even_with_identical_text(): void
    {
        UiFeedback::success('Ação concluída.');
        $first = session('ui_feedback_status_id');
        UiFeedback::success('Ação concluída.');
        $this->assertNotSame($first, session('ui_feedback_status_id'));
        $this->assertSame('Ação concluída.', session('status'));
        UiFeedback::error('Revise as informações.');
        $this->assertNotEmpty(session('ui_feedback_error_id'));
    }

    public function test_validation_and_render_do_not_create_success_or_change_feedback_identity(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $screen = Livewire::actingAs($manager)->test(SectorManager::class)->call('openCreateModal');
        $screen->call('save')->assertHasErrors('name')->assertSet('showFormModal', true);
        $this->assertNull(session('ui_feedback_status_id'));
        $screen->set('name', 'Setor feedback')->call('save')->assertHasNoErrors();
        $this->assertSame(1, preg_match('/wire:key="feedback-([a-f0-9-]{36})"/', $screen->html(), $match));
        $identity = $match[1];
        $this->assertDatabaseHas('sectors', ['name' => 'Setor feedback']);
        $screen->call('$refresh');
        if (preg_match('/wire:key="feedback-([a-f0-9-]{36})"/', $screen->html(), $refreshed)) {
            $this->assertSame($identity, $refreshed[1]);
        }
        $this->assertSame(1, Sector::where('name', 'Setor feedback')->count());
    }

    public function test_submit_buttons_use_the_form_lock_without_a_second_disabled_attribute_owner(): void
    {
        $submit = Blade::render('<x-action-button type="submit" target="save">Salvar</x-action-button>');
        $this->assertStringNotContainsString('wire:loading.attr="disabled"', $submit);
        $click = Blade::render('<x-action-button type="button" target="delete">Excluir</x-action-button>');
        $this->assertStringContainsString('wire:loading.attr="disabled"', $click);
    }

    public function test_authenticated_and_guest_layouts_use_the_shared_feedback_outlet(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('data-feedback-outlet', false);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager)->get(route('manager.sectors'))->assertOk()->assertSee('data-feedback-outlet', false);
    }
}
