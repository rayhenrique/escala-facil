<?php

namespace Tests\Feature;

use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseFiveInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_dashboard_displays_real_coverage_alerts(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $uncoveredShift = $this->shift($category, $sector, null, ShiftStatus::Uncovered, 2);
        Absence::query()->create([
            'shift_id' => $uncoveredShift->id,
            'user_id' => $requester->id,
            'reason' => 'Atendimento médico comprovado.',
            'status' => AbsenceStatus::Pending,
        ]);

        $swapShift = $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 3);
        ShiftSwap::query()->create([
            'shift_id' => $swapShift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::PendingManager,
        ]);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertSee('Cobertura em tempo real')
            ->assertSee('Plantões futuros descobertos')
            ->assertSee('Atendimento médico comprovado.')
            ->assertSee($requester->name)
            ->assertSee($substitute->name)
            ->assertSee('Aguardando decisão');
    }

    public function test_dashboard_alerts_only_include_operational_statuses(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $scheduledShift = $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 4);
        ShiftSwap::query()->create([
            'shift_id' => $scheduledShift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::PendingProfessional,
        ]);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertSee('Todos os plantões futuros têm cobertura.')
            ->assertSee('Nenhuma troca aguarda aprovação.')
            ->assertDontSee('Aguardando decisão');
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

    private function shift(
        Category $category,
        Sector $sector,
        ?User $professional,
        ShiftStatus $status,
        int $daysAhead,
    ): Shift {
        return Shift::query()->create([
            'date' => today()->addDays($daysAhead),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional?->id,
            'status' => $status,
        ]);
    }
}
