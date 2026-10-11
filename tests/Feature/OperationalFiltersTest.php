<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Reports;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftReportService;
use App\Support\OperationalNavigation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class OperationalFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::factory()->create(['name' => 'Emergência']);
        $category = Category::factory()->create(['name' => 'Medicina']);
        $ana = User::factory()->create(['name' => 'Ana Pesquisa', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $bruno = User::factory()->create(['name' => 'Bruno Pesquisa', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $this->actingAs($manager);

        return [$manager, $ana, $bruno, $sector, $category];
    }

    private function shift(User $user, string $date, array $overrides = []): Shift
    {
        return Shift::factory()->create($overrides + ['user_id' => $user->id, 'sector_id' => $user->sector_id, 'category_id' => $user->category_id, 'date' => $date]);
    }

    public function test_personal_lists_are_paginated_and_forged_sector_does_not_expose_records(): void
    {
        [, $ana, $bruno] = $this->team();
        $foreign = User::factory()->create(['name' => 'Equipe externa', 'sector_id' => Sector::factory(), 'category_id' => $ana->category_id]);
        for ($i = 0; $i < 12; $i++) {
            $this->shift($ana, '2026-11-01');
        }
        $outside = $this->shift($foreign, '2026-11-01');
        $own = Shift::query()->where('user_id', $ana->id)->first();
        $swap = ShiftSwap::factory()->create(['shift_id' => $own->id, 'requester_id' => $ana->id, 'substitute_id' => $bruno->id, 'status' => ShiftSwapStatus::Rejected]);
        ShiftSwap::factory()->create(['shift_id' => $outside->id, 'requester_id' => $foreign->id, 'substitute_id' => $bruno->id]);
        Livewire::actingAs($ana)->test(MySchedule::class, ['month' => '2026-11', 'filterSectorId' => (string) $foreign->sector_id])
            ->assertSet('filterSectorId', (string) $ana->sector_id)
            ->assertViewHas('shifts', fn ($rows) => $rows->total() === 12 && $rows->count() === 10)
            ->call('setPage', 2, 'shiftsPage')->assertViewHas('shifts', fn ($rows) => $rows->count() === 2)
            ->set('search', 'Bruno')->assertSet('paginators.shiftsPage', 1)
            ->set('swapStatusFilter', 'rejected')->assertViewHas('mySwaps', fn ($rows) => $rows->pluck('id')->all() === [$swap->id])
            ->assertDontSee('Equipe externa')->set('filterSectorId', (string) $foreign->sector_id)
            ->assertSet('filterSectorId', (string) $ana->sector_id)
            ->set('dateTo', 'bad-date')->assertHasErrors('dateTo')->assertViewHas('shifts', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->assertHasNoErrors()->assertSet('filterSectorId', (string) $ana->sector_id);
        $this->actingAs($ana)->get(route('professional.dashboard', ['search' => ['forged']]))->assertNotFound();
    }

    public function test_one_sided_periods_and_uncovered_active_scope_keep_their_meaning(): void
    {
        [, $ana] = $this->team();
        $this->travelTo(CarbonImmutable::parse('2026-10-31 01:00'));
        $active = $this->shift($ana, '2026-10-30', ['user_id' => null, 'status' => ShiftStatus::Uncovered, 'start_time' => '19:00', 'end_time' => '07:00', 'end_date' => '2026-10-31']);
        $future = $this->shift($ana, '2026-11-01', ['user_id' => null, 'status' => ShiftStatus::Uncovered]);
        $ended = $this->shift($ana, '2026-10-29', ['user_id' => null, 'status' => ShiftStatus::Uncovered]);
        Livewire::test(ShiftManager::class, ['month' => '2026-10', 'filter' => 'uncovered'])
            ->assertViewHas('shifts', fn ($rows) => $rows->pluck('id')->all() === [$active->id, $future->id])
            ->set('dateTo', '2026-10-30')->assertHasNoErrors()
            ->assertViewHas('shifts', fn ($rows) => $rows->pluck('id')->all() === [$active->id])
            ->call('clearFilters')->set('dateFrom', '2026-11-01')->assertHasNoErrors()
            ->assertViewHas('shifts', fn ($rows) => $rows->pluck('id')->all() === [$future->id]);
        $this->travelBack();
    }

    public function test_report_professional_search_is_literal_and_named_pages_survive_navigation(): void
    {
        [$manager, $ana, $bruno] = $this->team();
        $ana->update(['name' => 'Ana %_Teste']);
        $this->shift($ana, '2026-11-01');
        $this->shift($bruno, '2026-11-01');
        $report = app(ShiftReportService::class)->forScreen('2026-11', '', '', $manager, '%_');
        $this->assertSame(1, $report['byProfessional']->total());
        $this->assertSame('Ana %_Teste', $report['byProfessional']->first()['name']);
        $state = ['month' => '2026-11', 'tab' => 'swaps', 'swapsPage' => '2', 'professionalsPage' => '3', 'absencesPage' => '1'];
        $this->assertEquals($state, OperationalNavigation::clean('reports', $state));
        $this->assertNull(OperationalNavigation::id(''));
        $this->assertSame(['swapStatusFilter' => ''], OperationalNavigation::clean('swaps', ['swapStatusFilter' => '']));
        Livewire::test(Reports::class, ['month' => '2026-11'])->set('swapStatusFilter', 'forged')->assertHasErrors('swapStatusFilter')->assertSee('0 troca(s)');
    }

    public function test_shift_filters_combine_override_month_and_keep_edit_state_separate(): void
    {
        [$manager, $ana, $bruno, $sector, $category] = $this->team();
        $wanted = $this->shift($ana, '2026-11-01');
        $this->shift($ana, '2026-10-30');
        $this->shift($bruno, '2026-11-01');
        $this->shift($ana, '2026-11-02', ['status' => ShiftStatus::AbsenceReported]);
        Livewire::test(ShiftManager::class, ['month' => '2026-10'])
            ->set('dateFrom', '2026-10-31')->set('dateTo', '2026-11-02')
            ->set('search', '  ANA  ')->set('filterSectorId', (string) $sector->id)
            ->set('filterCategoryId', (string) $category->id)->set('filter', 'scheduled')
            ->assertViewHas('shifts', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $wanted->id)
            ->assertSet('sectorId', '')->assertSet('categoryId', '')
            ->call('clearFilters')->assertSet('search', '')->assertSet('dateFrom', '')->assertSet('filter', '')
            ->assertSet('month', '2026-10');
    }

    public function test_pagination_is_stable_and_restarts_when_filters_change(): void
    {
        [, $ana] = $this->team();
        $ids = [];
        for ($i = 0; $i < 13; $i++) {
            $ids[] = $this->shift($ana, '2026-11-01')->id;
        }
        Livewire::test(ShiftManager::class, ['month' => '2026-11'])
            ->assertViewHas('shifts', fn ($rows) => $rows->total() === 13 && $rows->pluck('id')->all() === array_slice($ids, 0, 10))
            ->call('setPage', 2)->assertViewHas('shifts', fn ($rows) => $rows->pluck('id')->all() === array_slice($ids, 10))
            ->set('search', 'Ana')->assertViewHas('shifts', fn ($rows) => $rows->currentPage() === 1 && $rows->count() === 10);
    }

    public function test_invalid_filters_fail_closed_and_can_be_cleared(): void
    {
        [, $ana] = $this->team();
        $this->shift($ana, '2026-11-01');
        Livewire::test(ShiftManager::class, ['month' => '2026-11'])
            ->set('dateFrom', '2026-11-02')->set('dateTo', '2026-11-01')
            ->assertHasErrors('dateTo')->assertViewHas('shifts', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->set('filterSectorId', '999999')->assertHasErrors('filterSectorId')
            ->assertViewHas('shifts', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->set('search', str_repeat('a', 101))->assertHasErrors('search')
            ->call('clearFilters')->assertHasNoErrors();
        foreach ([['search' => ['Ana']], ['dateFrom' => '2026-02-31'], ['filterSectorId' => '-1'], ['swapsPage' => '0']] as $params) {
            $this->get(route('manager.shifts', $params))->assertNotFound();
        }
    }

    public function test_percent_and_underscore_are_literal_search_characters(): void
    {
        [, $ana, $bruno] = $this->team();
        $ana->update(['name' => 'Ana 100%_plantões']);
        $wanted = $this->shift($ana, '2026-11-01');
        $this->shift($bruno, '2026-11-01');
        Livewire::test(ShiftManager::class, ['month' => '2026-11'])->set('search', '%_')
            ->assertViewHas('shifts', fn ($rows) => $rows->pluck('id')->all() === [$wanted->id]);
    }

    public function test_swap_filters_search_both_participants_and_primary_shift_with_status(): void
    {
        [, $ana, $bruno, $sector, $category] = $this->team();
        $primary = $this->shift($ana, '2026-11-01');
        $swap = ShiftSwap::factory()->create(['shift_id' => $primary->id, 'requester_id' => $ana->id, 'substitute_id' => $bruno->id, 'status' => ShiftSwapStatus::Rejected]);
        $component = Livewire::test(SwapApprovals::class)
            ->assertViewHas('swaps', fn ($rows) => $rows->total() === 0)
            ->set('swapStatusFilter', 'rejected')->set('search', 'Bruno')
            ->set('filterSectorId', (string) $sector->id)->set('filterCategoryId', (string) $category->id)
            ->set('dateFrom', '2026-11-01')->set('dateTo', '2026-11-01')
            ->assertViewHas('swaps', fn ($rows) => $rows->pluck('id')->all() === [$swap->id])
            ->assertDontSeeHtml('wire:click="requestDecision(')
            ->set('swapStatusFilter', 'forged')->assertHasErrors('swapStatusFilter')
            ->assertViewHas('swaps', fn ($rows) => $rows->total() === 0)
            ->call('clearFilters')->assertSet('swapStatusFilter', '')->assertSet('listView', 'history')->assertSet('search', '');
        $this->assertSame(ShiftSwapStatus::Rejected, $swap->fresh()->status);
    }

    public function test_report_screen_aggregates_match_pdf_and_lists_are_paginated_in_sql(): void
    {
        [$manager, $ana, $bruno] = $this->team();
        $shift = $this->shift($ana, '2026-11-01');
        $this->shift($bruno, '2026-11-02', ['user_id' => null, 'status' => ShiftStatus::Uncovered]);
        for ($i = 0; $i < 13; $i++) {
            ShiftSwap::factory()->create(['shift_id' => $shift->id, 'requester_id' => $ana->id, 'substitute_id' => $bruno->id, 'status' => $i % 2 ? ShiftSwapStatus::Rejected : ShiftSwapStatus::Approved, 'created_at' => '2026-11-05']);
        }
        $service = app(ShiftReportService::class);
        $pdf = $service->forPeriod('2026-11', '', '', $manager);
        DB::enableQueryLog();
        $screen = $service->forScreen('2026-11', '', '', $manager);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertEquals($pdf['summary'], $screen['summary']);
        $this->assertEquals($pdf['byProfessional']->all(), $screen['byProfessional']->items());
        $this->assertSame($pdf['bySector']->all(), $screen['bySector']->all());
        $this->assertSame(13, $screen['swaps']->total());
        $this->assertCount(10, $screen['swaps']);
        $this->assertLessThan(28, count($queries));
        $this->assertTrue(collect($queries)->contains(fn ($query) => str_contains(strtolower($query['query']), 'limit 10')));
        Livewire::test(Reports::class, ['month' => '2026-11', 'tab' => 'swaps'])
            ->call('setPage', 2, 'swapsPage')->assertSet('paginators.swapsPage', 2)
            ->set('swapStatusFilter', 'rejected')->assertSet('paginators.swapsPage', 1)
            ->assertSee('6 troca(s)')->call('clearFilters')->assertSet('swapStatusFilter', '');
    }

    public function test_professional_filters_cannot_escape_sector_or_own_swap_visibility(): void
    {
        [, $ana, $bruno] = $this->team();
        $other = User::factory()->create(['name' => 'Setor Restrito', 'sector_id' => Sector::factory(), 'category_id' => Category::factory()]);
        $foreign = $this->shift($other, '2026-11-01');
        $own = $this->shift($ana, '2026-11-01');
        ShiftSwap::factory()->create(['shift_id' => $foreign->id, 'requester_id' => $other->id, 'substitute_id' => $bruno->id, 'created_at' => '2026-11-01']);
        ShiftSwap::factory()->create(['shift_id' => $own->id, 'requester_id' => $bruno->id, 'substitute_id' => $bruno->id, 'created_at' => '2026-11-01']);
        Livewire::actingAs($ana)->test(Reports::class, ['month' => '2026-11', 'sectorId' => (string) $other->sector_id])
            ->set('searchProfessional', 'Setor Restrito')->set('swapStatusFilter', '')
            ->assertSet('sectorId', (string) $ana->sector_id)->assertDontSee('Setor Restrito')
            ->assertSee('0 profissional(is)')->assertSee('0 troca(s)');
        Livewire::actingAs($ana)->test(ShiftManager::class, ['search' => 'Ana'])->assertForbidden();
        Livewire::actingAs($ana)->test(SwapApprovals::class, ['swapStatusFilter' => ''])->assertForbidden();
    }

    public function test_monthly_swap_range_uses_index_without_full_scan_or_temporary_sort(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('SQLite query-plan regression; verify the production plan in MySQL.');
        }
        $plan = collect(DB::select('EXPLAIN QUERY PLAN SELECT id FROM shift_swaps WHERE created_at >= ? AND created_at < ? ORDER BY created_at, id LIMIT 10', ['2026-10-01', '2026-11-01']))->pluck('detail')->implode(' ');
        $this->assertStringContainsString('SEARCH shift_swaps', $plan);
        $this->assertStringNotContainsString('SCAN shift_swaps', $plan);
        $this->assertStringNotContainsString('TEMP B-TREE', $plan);
    }

    public function test_context_preserves_combined_filters_and_exact_records_ignore_list_filters(): void
    {
        [, $ana] = $this->team();
        $shift = $this->shift($ana, '2026-11-01');
        $state = ['month' => '2026-10', 'filter' => 'scheduled', 'search' => 'Inexistente', 'filterSectorId' => (string) $ana->sector_id, 'filterCategoryId' => (string) $ana->category_id, 'dateFrom' => '2026-10-01', 'dateTo' => '2026-10-02', 'page' => '2', 'from' => 'dashboard', 'context' => ['period' => '7']];
        $this->assertEquals($state, OperationalNavigation::clean('shifts', $state));
        $state = OperationalNavigation::clean('shifts', $state);
        $state['page'] = '1';
        $this->assertTrue(OperationalNavigation::validStateShape($state));
        Livewire::test(ShiftManager::class, $state + ['focus' => $shift->id])
            ->assertViewHas('shifts', fn ($rows) => $rows->first()->id === $shift->id)
            ->assertSeeHtml('href="'.e(route('manager.shifts', $state)).'"');
    }
}
