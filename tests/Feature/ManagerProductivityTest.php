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

    public function test_dashboard_prioritizes_next_72_hours_and_oldest_pending_swap(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $imminent = $this->shift($category, $sector, null, ShiftStatus::Uncovered, 1);
        $this->shift($category, $sector, null, ShiftStatus::Uncovered, 10);

        $oldestSwap = $this->pendingSwap($requester, $substitute, $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 4));
        $oldestSwap->update(['created_at' => now()->subDays(3), 'updated_at' => now()->subDays(3)]);
        $recentSwap = $this->pendingSwap($requester, $substitute, $this->shift($category, $sector, $requester, ShiftStatus::Scheduled, 5));

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertSee('Próximas 72 horas')
            ->assertSee('1 descoberto(s) em 72h')
            ->assertSee($imminent->date->format('d/m/Y'))
            ->assertSee('Realocar profissional')
            ->assertSee('Pendência mais antiga')
            ->assertSee('Pedido #'.$oldestSwap->id)
            ->assertSee($requester->name)
            ->assertSee($substitute->name)
            ->assertSee('Analisar solicitação')
            // Listas completas preservadas abaixo do foco.
            ->assertSee('Plantões futuros descobertos')
            ->assertSee('Trocas aguardando aprovação')
            ->assertSee('Pedido #'.$recentSwap->id);
    }

    public function test_dashboard_shows_empty_focus_state_while_lists_stay_complete(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $farShift = $this->shift($category, $sector, null, ShiftStatus::Uncovered, 10);

        Livewire::actingAs($manager)
            ->test(Dashboard::class)
            ->assertSee('Nenhuma pendência nas próximas 72 horas.')
            ->assertSee($farShift->date->format('d/m/Y'));
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
