<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\Reports;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\OperationalNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $this->actingAs($manager);

        return $manager;
    }

    public function test_dashboard_links_keep_period_and_open_highlighted_record_despite_unrelated_filters(): void
    {
        $this->manager();
        $shift = Shift::factory()->uncovered()->create(['date' => today()->addDay()]);
        $component = Livewire::test(Dashboard::class)->set('period', '7');
        $url = OperationalNavigation::url('shifts', ['focus' => $shift->id], 'dashboard', ['period' => '7', 'page' => '1']);
        $component->assertSeeHtml('href="'.e($url).'"');
        $back = route('manager.dashboard', ['period' => '7', 'page' => '2']);
        $this->get(route('manager.shifts', ['focus' => $shift->id, 'month' => '1999-01', 'filter' => 'scheduled', 'page' => '99', 'from' => 'dashboard', 'context' => ['period' => '7', 'page' => '2']]))
            ->assertOk()->assertSee('Plantão #'.$shift->id.' selecionado')
            ->assertSee('data-context-record="shift-'.$shift->id.'"', false)
            ->assertSee('href="'.e($back).'"', false)->assertSee($shift->time_label);
        Livewire::test(ShiftManager::class, ['focus' => $shift->id, 'month' => '1999-01', 'filter' => 'scheduled'])
            ->assertViewHas('shifts', fn ($shifts) => $shifts->pluck('id')->all() === [$shift->id])
            ->assertSet('focus', $shift->id);
    }

    public function test_request_links_work_after_status_changes_without_offering_invalid_decisions(): void
    {
        $this->manager();
        foreach (ShiftSwapStatus::cases() as $status) {
            $swap = ShiftSwap::factory()->create(['status' => $status]);
            $component = Livewire::test(SwapApprovals::class, ['request' => $swap->id, 'from' => 'reports', 'context' => ['month' => now()->format('Y-m'), 'tab' => 'swaps', 'swapStatusFilter' => $status->value]])
                ->assertSee('Pedido #'.$swap->id.' selecionado')
                ->assertSeeHtml('data-context-record="swap-'.$swap->id.'"')
                ->assertViewHas('swaps', fn ($swaps) => $swaps->pluck('id')->all() === [$swap->id])
                ->assertSee('Voltar ao relatório');
            if ($status !== ShiftSwapStatus::PendingManager) {
                $component->assertDontSeeHtml('wire:click="requestDecision(')->assertDontSee('Trocas aprovadas futuras');
            }
            $this->assertSame($status, $swap->fresh()->status);
            $this->get(route('manager.swaps', ['request' => $swap->id]))->assertOk()->assertSee('Pedido #'.$swap->id.' selecionado');
        }
    }

    public function test_ids_are_validated_on_requests_and_livewire_updates(): void
    {
        $this->manager();
        foreach (['bad', '1 OR 1=1', '-1', '0', '999999999999999999999999999', ['1'], '999999'] as $id) {
            $this->get(route('manager.shifts', ['focus' => $id]))->assertNotFound();
            $this->get(route('manager.swaps', ['request' => $id]))->assertNotFound();
            Livewire::test(ShiftManager::class)->set('focus', $id)->assertNotFound();
            Livewire::test(SwapApprovals::class)->set('request', $id)->assertNotFound();
        }
        $this->get(route('manager.shifts', ['month' => ['2027-01']]))->assertNotFound();
        $this->get(route('manager.calendar', ['selectedDate' => '2027-02-31']))->assertNotFound();
        $this->get(route('manager.reports', ['context' => ['month' => ['nested']]]))->assertNotFound();
    }

    public function test_manager_record_routes_and_livewire_remain_inaccessible_to_professionals(): void
    {
        $this->manager();
        $shift = Shift::factory()->create();
        $swap = ShiftSwap::factory()->create(['shift_id' => $shift->id]);
        $professional = User::factory()->create();
        $this->actingAs($professional);
        $this->get(route('manager.shifts', ['focus' => $shift->id, 'from' => 'reports']))->assertForbidden();
        $this->get(route('manager.swaps', ['request' => $swap->id, 'from' => 'calendar']))->assertForbidden();
        Livewire::test(ShiftManager::class, ['focus' => $shift->id])->assertForbidden();
        Livewire::test(SwapApprovals::class, ['request' => $swap->id])->assertForbidden();
        $this->assertFalse($professional->can('view', $shift));
        $this->assertFalse($professional->can('view', $swap));
    }

    public function test_return_targets_are_internal_whitelisted_and_role_scoped(): void
    {
        $this->manager();
        foreach (['https://evil.example', '//evil.example', '../users', ['dashboard']] as $from) {
            $back = OperationalNavigation::back($from, ['url' => 'https://evil.example', 'month' => 'bad', 'period' => '7', 'page' => '2']);
            $this->assertSame(route('manager.dashboard', ['period' => '7', 'page' => '2']), $back['url']);
        }
        $back = OperationalNavigation::back('calendar', ['month' => '2027-02', 'selectedDate' => '2027-02-31', 'sectorId' => 'bad', 'url' => 'https://evil.example']);
        $this->assertSame(route('manager.calendar', ['month' => '2027-02']), $back['url']);
        $this->actingAs(User::factory()->create());
        $back = OperationalNavigation::back('shifts', ['focus' => '1', 'month' => '2027-02']);
        $this->assertSame(route('professional.calendar', ['month' => '2027-02']), $back['url']);
    }

    public function test_calendar_deep_links_keep_date_and_filters_and_address_mutual_counterpart(): void
    {
        $this->manager();
        $counterpart = Shift::factory()->create(['date' => today()]);
        $primary = Shift::factory()->create(['date' => today()->addDays(5)]);
        $swap = ShiftSwap::factory()->create(['shift_id' => $primary->id, 'substitute_shift_id' => $counterpart->id, 'status' => ShiftSwapStatus::PendingManager]);
        $state = ['month' => now()->format('Y-m'), 'selectedDate' => today()->format('Y-m-d'), 'sectorId' => (string) $counterpart->sector_id, 'categoryId' => (string) $counterpart->category_id];
        $returnState = $state + ['agendaView' => 'week', 'agendaPage' => '1'];
        $target = OperationalNavigation::url('swaps', ['request' => $swap->id], 'calendar', $returnState);
        $this->get(route('manager.calendar', $state))->assertOk()->assertSee('href="'.e($target).'"', false);
        $this->get($target)->assertOk()->assertSee('href="'.e(route('manager.calendar', $returnState)).'"', false);
    }

    public function test_professional_shared_links_cannot_change_sector_scope_or_expose_other_peoples_requests(): void
    {
        $own = Shift::factory()->create(['date' => today()]);
        $own->user->update(['category_id' => $own->category_id, 'sector_id' => $own->sector_id]);
        $outside = Shift::factory()->create(['date' => today()]);
        $outside->sector->update(['name' => 'Setor proibido via URL']);
        $professional = User::factory()->create(['sector_id' => $own->sector_id, 'category_id' => $own->category_id]);
        ShiftSwap::factory()->create(['shift_id' => $outside->id]);
        $this->actingAs($professional);
        $parameters = ['month' => now()->format('Y-m'), 'sectorId' => $outside->sector_id, 'from' => 'shifts'];
        $this->get(route('professional.calendar', $parameters))->assertOk()->assertDontSee('Setor proibido via URL');
        $this->get(route('professional.reports', $parameters))->assertOk()->assertDontSee('Setor proibido via URL')->assertDontSee('Abrir pedido #');
        Livewire::test(Reports::class, $parameters)->set('tab', 'swaps')->set('sectorId', (string) $outside->sector_id)
            ->assertSet('sectorId', (string) $own->sector_id)->assertViewHas('routePrefix', 'professional.')
            ->assertDontSee('Setor proibido via URL');
    }

    public function test_deleting_selected_shift_returns_to_list_instead_of_missing_record_error(): void
    {
        $this->manager();
        $shift = Shift::factory()->create();
        Livewire::test(ShiftManager::class, ['focus' => $shift->id])->call('requestDelete', $shift->id)
            ->call('delete')->assertOk()->assertSet('focus', null);
        $this->assertModelMissing($shift);
    }

    public function test_nested_return_context_preserves_prior_steps_and_limits_untrusted_depth(): void
    {
        $this->manager();
        $shift = Shift::factory()->create();
        $shiftState = ['focus' => (string) $shift->id, 'month' => '2027-02', 'from' => 'dashboard', 'context' => ['period' => '7', 'page' => '2']];
        $calendarState = ['month' => '2027-02', 'selectedDate' => '2027-02-12', 'from' => 'shifts', 'context' => $shiftState];
        $reportUrl = OperationalNavigation::url('reports', ['month' => '2027-02', 'tab' => 'coverage'], 'calendar', $calendarState);
        $calendarBack = OperationalNavigation::back('calendar', $calendarState)['url'];
        $this->get($reportUrl)->assertOk()->assertSee('href="'.e($calendarBack).'"', false);
        $shiftBack = OperationalNavigation::back('shifts', $shiftState)['url'];
        $this->get($calendarBack)->assertOk()->assertSee('href="'.e($shiftBack).'"', false);
        $this->get($shiftBack)->assertOk()->assertSee('href="'.e(route('manager.dashboard', ['period' => '7', 'page' => '2'])).'"', false);
        $deep = ['month' => '2027-02'];
        for ($i = 0; $i < 10; $i++) {
            $deep = ['from' => 'calendar', 'context' => $deep];
        }
        $this->assertFalse(OperationalNavigation::validStateShape($deep));
        $this->assertTrue(OperationalNavigation::validStateShape(OperationalNavigation::clean('calendar', $deep)));
    }

    public function test_return_to_queue_uses_last_available_page_after_work_is_resolved(): void
    {
        $this->manager();
        $shifts = Shift::factory()->uncovered()->count(12)->create(['date' => today()->addDay()]);
        $component = Livewire::test(Dashboard::class)->call('gotoPage', 2);
        foreach ($shifts->take(8) as $shift) {
            $shift->update(['status' => ShiftStatus::Scheduled]);
        }
        $component->call('$refresh')->assertSet('paginators.page', 1)
            ->assertViewHas('queue', fn ($queue) => $queue->total() === 4 && $queue->count() === 4);
        $this->get(route('manager.dashboard', ['page' => '999', 'period' => '3']))->assertOk()->assertSee('4 pendências no período');
    }
}
