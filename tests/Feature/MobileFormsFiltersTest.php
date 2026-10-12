<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Livewire\Manager\CategoryManager;
use App\Livewire\Manager\SectorManager;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\UserManager;
use App\Models\Category;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MobileFormsFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_filter_clear_resets_pagination_without_changing_records(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        foreach ([UserManager::class, SectorManager::class, CategoryManager::class] as $class) {
            $screen = Livewire::actingAs($manager)->test($class)->set('search', 'Sem resultado')->call('setPage', 2);
            if ($class === UserManager::class) {
                $screen->set('roleFilter', 'professional');
            }
            $screen->call('clearFilters')->assertSet('search', '')->assertSet('paginators.page', 1);
            if ($class === UserManager::class) {
                $screen->assertSet('roleFilter', '');
            }
        }
        $this->assertDatabaseHas('users', ['id' => $manager->id, 'role' => 'manager']);
    }

    public function test_professional_search_is_bounded_preserves_selection_and_sector_category(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::factory()->create();
        $category = Category::factory()->create();
        User::factory()->count(51)->create(['sector_id' => $sector->id, 'category_id' => $category->id]);
        $selected = User::factory()->create(['name' => 'ZZ profissional selecionado', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $found = User::factory()->create(['name' => 'Nome pesquisável', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $outside = User::factory()->create(['name' => 'Nome pesquisável externo', 'category_id' => $category->id]);
        $screen = Livewire::actingAs($manager)->test(ShiftManager::class)->call('openCreateModal')
            ->set('sectorId', (string) $sector->id)->set('categoryId', (string) $category->id)->set('userId', (string) $selected->id);
        $this->assertCount(50, $screen->instance()->professionals);
        $this->assertTrue($screen->instance()->professionals->contains('id', $selected->id));
        $screen->set('professionalSearch', 'pesquisável')->assertSet('userId', (string) $selected->id);
        $this->assertSame([$selected->id, $found->id], $screen->instance()->professionals->pluck('id')->all());
        $this->assertFalse($screen->instance()->professionals->contains('id', $outside->id));
        $screen->call('closeFormModal')->assertSet('professionalSearch', '');
    }

    public function test_touched_forms_reuse_dialog_validation_and_manager_authorization(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        foreach ([UserManager::class, CategoryManager::class, ShiftManager::class] as $class) {
            Livewire::actingAs($manager)->test($class)->call('openCreateModal')->assertSeeHtml('data-ui-dialog-component=')
                ->assertSee('Descartar alterações')->call('save')->assertHasErrors()
                ->assertSet('showFormModal', true)->assertSeeHtml('aria-invalid="true"');
            Livewire::actingAs(User::factory()->create())->test($class)->assertForbidden();
        }
    }
}
