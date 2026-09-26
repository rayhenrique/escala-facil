<?php

namespace Tests\Feature;

use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\Professional\MySchedule;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseFourWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_create_update_and_delete_a_compatible_shift(): void
    {
        [$manager, $requester, , $category, $sector] = $this->team();

        $component = Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->assertSet('showFormModal', false)
            ->call('openCreateModal')
            ->assertSet('showFormModal', true)
            ->set('date', today()->addDays(3)->format('Y-m-d'))
            ->set('startTime', '07:00')
            ->set('endTime', '19:00')
            ->set('sectorId', (string) $sector->id)
            ->set('categoryId', (string) $category->id)
            ->set('userId', (string) $requester->id)
            ->set('status', ShiftStatus::Scheduled->value)
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $shift = Shift::query()->sole();
        $this->assertSame($requester->id, $shift->user_id);

        $component
            ->call('edit', $shift->id)
            ->assertSet('showFormModal', true)
            ->set('endTime', '20:00')
            ->call('save')
            ->assertSet('showFormModal', false)
            ->assertHasNoErrors();

        $this->assertSame('20:00', substr((string) $shift->refresh()->end_time, 0, 5));

        $component->call('requestDelete', $shift->id)->call('delete');
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_manager_cannot_assign_a_professional_from_another_sector(): void
    {
        [$manager, , , $category, $sector] = $this->team();
        $otherSector = Sector::query()->create(['name' => 'Centro cirúrgico']);
        $incompatible = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $otherSector->id,
        ]);

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->set('date', today()->addDays(3)->format('Y-m-d'))
            ->set('startTime', '07:00')
            ->set('endTime', '19:00')
            ->set('sectorId', (string) $sector->id)
            ->set('categoryId', (string) $category->id)
            ->set('userId', (string) $incompatible->id)
            ->call('save')
            ->assertHasErrors(['user_id']);

        $this->assertDatabaseCount('shifts', 0);
    }

    public function test_complete_swap_flow_transfers_the_shift_after_manager_approval(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();
        $shift = $this->shift($requester, $category, $sector);

        Livewire::actingAs($requester)
            ->test(MySchedule::class)
            ->call('openSwap', $shift->id)
            ->set('substituteId', (string) $substitute->id)
            ->call('requestSwap')
            ->assertHasNoErrors();

        $swap = ShiftSwap::query()->sole();
        $this->assertSame(ShiftSwapStatus::PendingProfessional, $swap->status);

        Livewire::actingAs($substitute)
            ->test(MySchedule::class)
            ->call('acceptSwap', $swap->id)
            ->assertHasNoErrors();

        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);

        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->call('approve', $swap->id)
            ->assertHasNoErrors();

        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
        $this->assertSame($manager->id, $swap->manager_id);
        $this->assertSame($substitute->id, $shift->refresh()->user_id);
    }

    public function test_incompatible_substitute_cannot_be_used_even_with_a_forged_request(): void
    {
        [, $requester, , $category, $sector] = $this->team();
        $otherSector = Sector::query()->create(['name' => 'Pediatria']);
        $incompatible = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $otherSector->id,
        ]);
        $shift = $this->shift($requester, $category, $sector);

        Livewire::actingAs($requester)
            ->test(MySchedule::class)
            ->set('swapShiftId', $shift->id)
            ->set('substituteId', (string) $incompatible->id)
            ->call('requestSwap')
            ->assertHasErrors(['substitute_id']);

        $this->assertDatabaseCount('shift_swaps', 0);
    }

    public function test_reporting_an_absence_stores_attachment_uncovers_shift_and_closes_open_swap(): void
    {
        Storage::fake('local');
        [, $requester, $substitute, $category, $sector] = $this->team();
        $shift = $this->shift($requester, $category, $sector);
        $swap = ShiftSwap::query()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::PendingProfessional,
        ]);

        Livewire::actingAs($requester)
            ->test(MySchedule::class)
            ->call('openAbsence', $shift->id)
            ->set('absenceReason', 'Atendimento médico de urgência comprovado.')
            ->set('attachment', UploadedFile::fake()->create('atestado.pdf', 120, 'application/pdf'))
            ->call('reportAbsence')
            ->assertHasNoErrors();

        $absence = Absence::query()->sole();
        $this->assertSame(AbsenceStatus::Pending, $absence->status);
        Storage::disk('local')->assertExists($absence->attachment_path);
        $this->assertNull($shift->refresh()->user_id);
        $this->assertSame(ShiftStatus::Uncovered, $shift->status);
        $this->assertSame(ShiftSwapStatus::Rejected, $swap->refresh()->status);
    }

    public function test_role_routes_reject_the_wrong_authenticated_profile(): void
    {
        [$manager, $professional] = $this->team();

        $this->actingAs($professional)->get('/manager/shifts')->assertForbidden();
        $this->actingAs($manager)->get('/professional/dashboard')->assertForbidden();
    }

    public function test_invalid_month_filters_are_normalized_before_queries_run(): void
    {
        [$manager, $professional] = $this->team();
        $currentMonth = now()->format('Y-m');

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->set('month', '2026-99')
            ->assertSet('month', $currentMonth)
            ->assertHasNoErrors();

        Livewire::actingAs($professional)
            ->test(MySchedule::class)
            ->set('month', '../../etc/passwd')
            ->assertSet('month', $currentMonth)
            ->assertHasNoErrors();
    }

    /** @return array{User, User, User, Category, Sector} */
    private function team(): array
    {
        $category = Category::query()->create(['name' => 'Médico Clínico']);
        $sector = Sector::query()->create(['name' => 'Emergência']);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $requester = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $substitute = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        return [$manager, $requester, $substitute, $category, $sector];
    }

    private function shift(User $requester, Category $category, Sector $sector): Shift
    {
        return Shift::query()->create([
            'date' => today()->addDays(5),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);
    }
}
