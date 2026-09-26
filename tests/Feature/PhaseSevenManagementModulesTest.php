<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\CategoryManager;
use App\Livewire\Manager\SectorManager;
use App\Livewire\Manager\UserManager;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseSevenManagementModulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_and_delete_categories_and_sectors(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $categories = Livewire::actingAs($manager)
            ->test(CategoryManager::class)
            ->assertSet('showFormModal', false)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('name', 'Fisioterapeuta')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $category = Category::query()->sole();
        $categories
            ->call('edit', $category->id)
            ->assertSet('showFormModal', true)
            ->set('name', 'Fisioterapia')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'Fisioterapia']);

        $categories->call('requestDelete', $category->id)->call('delete');
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);

        $sectors = Livewire::actingAs($manager)
            ->test(SectorManager::class)
            ->assertSet('showFormModal', false)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('name', 'Centro cirúrgico')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $sector = Sector::query()->sole();
        $sectors
            ->call('edit', $sector->id)
            ->assertSet('showFormModal', true)
            ->set('name', 'Bloco cirúrgico')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $this->assertDatabaseHas('sectors', ['id' => $sector->id, 'name' => 'Bloco cirúrgico']);

        $sectors->call('requestDelete', $sector->id)->call('delete');
        $this->assertDatabaseMissing('sectors', ['id' => $sector->id]);
    }

    public function test_manager_can_create_update_filter_and_delete_an_unlinked_user(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::query()->create(['name' => 'Enfermagem']);
        $sector = Sector::query()->create(['name' => 'Emergência']);

        $component = Livewire::actingAs($manager)
            ->test(UserManager::class)
            ->assertSet('showFormModal', false)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('name', 'Maria da Silva')
            ->set('email', 'MARIA@EXAMPLE.COM')
            ->set('role', UserRole::Professional->value)
            ->set('categoryId', (string) $category->id)
            ->set('sectorId', (string) $sector->id)
            ->set('password', 'senha-segura')
            ->set('passwordConfirmation', 'senha-segura')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $professional = User::query()->whereKeyNot($manager->id)->sole();
        $this->assertSame('maria@example.com', $professional->email);
        $this->assertSame($category->id, $professional->category_id);
        $this->assertSame($sector->id, $professional->sector_id);
        $passwordHash = $professional->password;

        $component
            ->call('edit', $professional->id)
            ->assertSet('showFormModal', true)
            ->set('name', 'Maria Souza')
            ->set('password', '')
            ->set('passwordConfirmation', '')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors()
            ->set('search', 'Maria Souza')
            ->set('roleFilter', UserRole::Professional->value)
            ->assertSee('Maria Souza');

        $this->assertSame($passwordHash, $professional->refresh()->password);

        $component->call('requestDelete', $professional->id)->call('delete');
        $this->assertDatabaseMissing('users', ['id' => $professional->id]);
    }

    public function test_linked_records_and_critical_accounts_are_protected_from_deletion(): void
    {
        $category = Category::query()->create(['name' => 'Médico Clínico']);
        $sector = Sector::query()->create(['name' => 'UTI']);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $professional = User::factory()->create([
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        Shift::query()->create([
            'date' => today()->addWeek(),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        Livewire::actingAs($manager)
            ->test(UserManager::class)
            ->call('requestDelete', $manager->id)
            ->call('delete')
            ->assertSee('Você não pode excluir a própria conta.');

        Livewire::actingAs($manager)
            ->test(UserManager::class)
            ->call('requestDelete', $professional->id)
            ->call('delete')
            ->assertSee('O usuário não pode ser excluído porque possui histórico de plantões, trocas ou ausências.');

        Livewire::actingAs($manager)
            ->test(CategoryManager::class)
            ->call('requestDelete', $category->id)
            ->call('delete')
            ->assertSee('A categoria não pode ser excluída porque está vinculada a usuários ou plantões.');

        Livewire::actingAs($manager)
            ->test(SectorManager::class)
            ->call('requestDelete', $sector->id)
            ->call('delete')
            ->assertSee('O setor não pode ser excluído porque está vinculado a usuários ou plantões.');

        $this->assertDatabaseHas('users', ['id' => $manager->id]);
        $this->assertDatabaseHas('users', ['id' => $professional->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('sectors', ['id' => $sector->id]);
    }

    public function test_professional_cannot_open_or_invoke_management_modules(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)->get(route('manager.users'))->assertForbidden();
        $this->actingAs($professional)->get(route('manager.sectors'))->assertForbidden();
        $this->actingAs($professional)->get(route('manager.categories'))->assertForbidden();

        Livewire::actingAs($professional)->test(UserManager::class)->assertForbidden();
        Livewire::actingAs($professional)->test(SectorManager::class)->assertForbidden();
        Livewire::actingAs($professional)->test(CategoryManager::class)->assertForbidden();
    }

    public function test_authenticated_layout_exposes_icon_sidebar_and_mobile_menu_control(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->actingAs($manager)
            ->get(route('manager.dashboard'))
            ->assertOk()
            ->assertSee('Barra lateral principal')
            ->assertSee('Abrir menu principal')
            ->assertSee('Recolher barra lateral')
            ->assertSeeInOrder(['Visão geral', 'Calendário', 'Escalas', 'Trocas', 'Setores', 'Categorias', 'Usuários'])
            ->assertSee('aria-current="page"', false)
            ->assertSee('sidebar-nav-icon');
    }
}
