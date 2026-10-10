<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Livewire\MonthlyCalendar;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ManagerProductivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_has_one_queue_with_operational_risk_before_proximity_and_age(): void
    {
        $this->travelTo(now()->startOfDay()->setHour(6));
        [$manager, $requester, $substitute, $category, $sector] = $this->team();
        $uncovered = $this->shift($category, $sector, null, ShiftStatus::Uncovered, 2);
        $nearSwap = $this->pendingSwap($requester, $substitute, $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 0));
        $laterShift = $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 1);
        $oldSwap = $this->pendingSwap($requester, $substitute, $laterShift);
        $oldSwap->update(['created_at' => now()->subDays(3)]);
        $newSwap = $this->pendingSwap($requester, $substitute, $laterShift);

        $component = Livewire::actingAs($manager)->test(Dashboard::class)
            ->assertSee('Fila de pendências')->assertDontSee('Pendência mais antiga')
            ->assertDontSee('Trocas aguardando aprovação')
            ->assertSeeInOrder(['Plantão #'.$uncovered->id, 'Pedido #'.$nearSwap->id, 'Pedido #'.$oldSwap->id, 'Pedido #'.$newSwap->id]);
        $this->assertSame(1, substr_count($component->html(), 'data-attention="swap-'.$oldSwap->id.'"'));
        $component->assertViewHas('pendingSwapCount', 3)->assertViewHas('uncoveredCount', 1);
    }

    public function test_dashboard_keeps_global_counts_when_period_is_empty(): void
    {
        [$manager, , , $category, $sector] = $this->team();
        $farShift = $this->shift($category, $sector, null, ShiftStatus::Uncovered, 10);
        Livewire::actingAs($manager)->test(Dashboard::class)
            ->assertSee('Nenhuma pendência neste período.')
            ->assertViewHas('uncoveredCount', 1)
            ->assertDontSee('data-attention="uncovered-'.$farShift->id.'"', false)
            ->assertSee(route('manager.shifts', ['filter' => 'uncovered']), false);
    }

    public function test_dashboard_calculation_queries_stay_bounded(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        foreach ([1, 2, 9] as $daysAhead) {
            $this->shift($category, $sector, null, ShiftStatus::Uncovered, $daysAhead);
        }

        $this->pendingSwap($requester, $substitute, $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 4));
        $this->pendingSwap($requester, $substitute, $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 5));

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });

        Livewire::actingAs($manager)->test(Dashboard::class)->assertOk();

        $this->assertLessThanOrEqual(30, $queries);
    }

    public function test_calendar_contextual_actions_respect_permissions(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $uncovered = Shift::query()->create([
            'date' => today(),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => null,
            'status' => ShiftStatus::Uncovered,
        ]);

        $swapShift = Shift::query()->create([
            'date' => today(),
            'start_time' => '19:00',
            'end_time' => '07:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);
        $this->pendingSwap($requester, $substitute, $swapShift);

        Shift::query()->create([
            'date' => today(),
            'start_time' => '07:00',
            'end_time' => '13:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $substitute->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->assertSee('Alocar profissional')
            ->assertSee('Analisar troca')
            ->assertSee('Abrir escala');

        Livewire::actingAs($requester)
            ->test(MonthlyCalendar::class)
            ->assertDontSee('Alocar profissional')
            ->assertDontSee('Analisar troca')
            ->assertDontSee('Abrir escala');

        $this->assertSame($uncovered->id, $uncovered->refresh()->id);
    }

    public function test_calendar_today_button_resets_month_and_selection(): void
    {
        [$manager] = $this->team();

        $nextMonth = today()->addMonth()->format('Y-m');

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', $nextMonth)
            ->assertSet('selectedDate', $nextMonth.'-01')
            ->call('goToToday')
            ->assertSet('month', now()->format('Y-m'))
            ->assertSet('selectedDate', today()->format('Y-m-d'))
            ->assertSee('Hoje');
    }

    public function test_professional_cannot_open_the_manager_dashboard(): void
    {
        [, $requester] = $this->team();

        $this->actingAs($requester)->get(route('manager.dashboard'))->assertForbidden();
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

    private function pendingSwap(User $requester, User $substitute, Shift $shift): ShiftSwap
    {
        return ShiftSwap::query()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::PendingManager,
        ]);
    }
}
