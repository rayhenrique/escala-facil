<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Reports;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftReportService;
use App\Support\OperationalNavigation;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class SectorReportsTest extends TestCase
{
    use RefreshDatabase;

    private function dataset(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::factory()->create(['name' => 'Emergência']);
        $category = Category::factory()->create(['name' => 'Enfermagem']);
        $professional = User::factory()->create(['sector_id' => $sector->id, 'category_id' => $category->id]);
        $base = ['sector_id' => $sector->id, 'category_id' => $category->id, 'user_id' => $professional->id];
        $day = Shift::factory()->create($base + ['date' => '2026-10-31', 'end_date' => '2026-11-01', 'start_time' => '07:00', 'end_time' => '07:00']);
        $night = Shift::factory()->night()->create($base + ['date' => '2026-10-30']);
        Shift::factory()->withoutUser()->create(array_replace($base, ['date' => '2026-10-03', 'user_id' => null]));
        $uncovered = Shift::factory()->uncovered()->create(array_replace($base, ['date' => '2026-10-04', 'user_id' => null]));
        Shift::factory()->create($base + ['date' => '2026-10-05', 'status' => ShiftStatus::AbsenceReported]);
        Shift::factory()->night()->create($base + ['date' => '2026-09-30']);
        $future = Shift::factory()->create($base + ['date' => '2026-11-01']);
        foreach ([['2026-10-01 00:00:00', ShiftSwapStatus::Approved], ['2026-10-31 23:59:59', ShiftSwapStatus::PendingManager], ['2026-11-01 00:00:00', ShiftSwapStatus::Approved], ['2026-09-30 23:59:59', ShiftSwapStatus::Approved]] as [$date, $status]) {
            ShiftSwap::factory()->create(['shift_id' => $future->id, 'requester_id' => $professional->id, 'substitute_id' => $manager->id, 'created_at' => $date, 'status' => $status]);
        }
        foreach ([$day, $day, $uncovered] as $shift) {
            Absence::factory()->create(['shift_id' => $shift->id, 'user_id' => $professional->id, 'created_at' => '2026-11-03 12:00:00']);
        }
        Absence::factory()->create(['shift_id' => $future->id, 'user_id' => $professional->id, 'created_at' => '2026-10-01 00:00:00']);

        return compact('manager', 'sector', 'category', 'professional', 'day', 'night', 'uncovered');
    }

    private function rows(array $data, string $category = '', string $status = '')
    {
        $service = app(ShiftReportService::class);

        return $service->sectorIndicators('2026-10', (string) $data['sector']->id, $category, $data['manager'], $status)
            ->get()->map($service->indicatorRow(...));
    }

    public function test_counts_are_not_multiplied_by_events_and_midnight_shifts_count_once(): void
    {
        $data = $this->dataset();
        $row = $this->rows($data)->sole();
        $this->assertSame(5, $row['total']);
        $this->assertSame(2, $row['covered']);
        $this->assertSame(40, $row['coverageRate']);
        $this->assertSame(3, $row['withoutCoverage']);
        $this->assertSame(1, $row['uncovered']);
        $this->assertSame(1, $row['absence_reported']);
        $this->assertSame(2, $row['swaps']);
        $this->assertSame(3, $row['absences']);
        $this->assertSame(1440, $data['day']->period()->durationMinutes);

        $screen = app(ShiftReportService::class)->forScreen('2026-10', (string) $data['sector']->id, '', $data['manager']);
        $pdf = app(ShiftReportService::class)->forPeriod('2026-10', (string) $data['sector']->id, '', $data['manager']);
        $this->assertEquals($screen['summary'], $pdf['summary']);
        $this->assertSame(2, $screen['summary']['covered']);
        $this->assertSame($row, $pdf['sectorIndicators']->sole());
    }

    public function test_history_survives_reallocation_while_current_state_changes(): void
    {
        $data = $this->dataset();
        $data['uncovered']->update(['user_id' => $data['professional']->id, 'status' => ShiftStatus::Scheduled]);
        $row = $this->rows($data)->sole();
        $this->assertSame(3, $row['covered']);
        $this->assertSame(0, $row['uncovered']);
        $this->assertSame(3, $row['absences']);
    }

    public function test_combined_category_and_status_filters_and_empty_sectors(): void
    {
        $data = $this->dataset();
        $other = Category::factory()->create();
        $row = $this->rows($data, (string) $data['category']->id, 'approved')->sole();
        $this->assertSame(5, $row['total']);
        $this->assertSame(1, $row['swaps']);
        $empty = $this->rows($data, (string) $other->id)->sole();
        foreach (['total', 'covered', 'uncovered', 'swaps', 'absences', 'withoutCoverage'] as $key) {
            $this->assertSame(0, $empty[$key]);
        }
        $this->assertNull($empty['coverageRate']);
        $service = app(ShiftReportService::class);
        $this->assertCount(0, $service->sectorIndicators('2026-10', '99999', '', $data['manager'])->get());
    }

    public function test_grouping_is_one_query_and_does_not_hydrate_shift_records(): void
    {
        $data = $this->dataset();
        $query = app(ShiftReportService::class)->sectorIndicators('2026-10', '', '', $data['manager']);
        DB::enableQueryLog();
        $query->get();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertStringNotContainsString('strftime', $queries[0]['query']);
        $this->assertStringContainsString('group by', $queries[0]['query']);
    }

    public function test_pagination_resets_with_filters_and_return_context_keeps_sector_page(): void
    {
        $data = $this->dataset();
        Sector::factory()->count(12)->create();
        Livewire::actingAs($data['manager'])->test(Reports::class, ['month' => '2026-10', 'tab' => 'coverage'])
            ->assertSee('13 setor(es)')->call('gotoPage', 2, 'sectorsPage')
            ->assertSet('paginators.sectorsPage', 2)
            ->set('sectorId', (string) $data['sector']->id)->assertSet('paginators.sectorsPage', 1)
            ->assertSee('1 setor(es)')->assertSee('Registros do período por setor')
            ->call('clearFilters')->assertSet('paginators.sectorsPage', 1);
        $this->assertSame(['sectorsPage' => '2'], OperationalNavigation::clean('reports', ['sectorsPage' => '2']));
        $this->actingAs($data['manager'])->get(route('manager.reports', ['sectorsPage' => '-1']))->assertNotFound();
    }

    public function test_manager_csv_exports_all_filtered_sectors_not_only_the_visible_page(): void
    {
        $data = $this->dataset();
        Sector::factory()->count(12)->create();
        $response = $this->actingAs($data['manager'])->get(route('manager.reports.sectors.csv', [
            'month' => '2026-10', 'sector_id' => $data['sector']->id, 'category_id' => $data['category']->id, 'swapStatusFilter' => 'approved',
        ]))->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Emergência;5;2;40;3;1;1;1;3', $csv);
        $this->assertStringContainsString('Não representa horas', $csv);
        $this->assertSame(6, substr_count($csv, "\n"));
        $all = $this->get(route('manager.reports.sectors.csv', ['month' => '2026-10']))->assertOk()->streamedContent();
        $this->assertSame(18, substr_count($all, "\n"));
    }

    public function test_csv_neutralizes_formula_names(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Sector::factory()->create(['name' => '=SUM(1)']);
        $csv = $this->actingAs($manager)->get(route('manager.reports.sectors.csv'))->assertOk()->streamedContent();
        $this->assertStringContainsString("'=SUM(1)", $csv);
    }

    public function test_exports_validate_filters_instead_of_silently_broadening_results(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        foreach (['manager.reports.sectors.csv', 'manager.reports.pdf'] as $route) {
            foreach ([['month' => '2026-99'], ['sector_id' => 999999], ['category_id' => ['1']], ['swapStatusFilter' => 'unknown'], ['searchProfessional' => str_repeat('a', 251)]] as $filters) {
                $this->actingAs($manager)->getJson(route($route, $filters))->assertUnprocessable();
            }
        }
    }

    public function test_pdf_passes_active_list_filters_and_sector_indicators(): void
    {
        $data = $this->dataset();
        $data['professional']->update(['name' => 'Ana']);
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $payload) use ($data) {
            $report = $payload['report'];
            $this->assertSame('reports.shifts', $view);
            $this->assertSame('Ana', $report['filters']['searchProfessional']);
            $this->assertSame('approved', $report['filters']['swapStatusFilter']);
            $this->assertSame(1, $report['sectorIndicators']->sole()['swaps']);
            $this->assertCount(1, $report['swaps']);
            $this->assertCount(1, $report['byProfessional']);
            $this->assertSame((string) $data['sector']->id, $report['filters']['sector_id']);

            return true;
        })->andReturnSelf();
        Pdf::shouldReceive('download')->once()->andReturn(response('PDF'));
        $this->actingAs($data['manager'])->get(route('manager.reports.pdf', [
            'month' => '2026-10', 'sector_id' => $data['sector']->id, 'category_id' => $data['category']->id,
            'searchProfessional' => 'Ana', 'swapStatusFilter' => 'approved',
        ]))->assertOk();
    }

    public function test_new_indicators_are_manager_only_and_professional_sector_is_enforced(): void
    {
        $data = $this->dataset();
        $other = Sector::factory()->create();
        $this->actingAs($data['professional'])->get(route('manager.reports.sectors.csv'))->assertForbidden();
        $this->get(route('professional.reports', ['month' => '2026-10', 'sectorId' => $other->id]))
            ->assertOk()->assertDontSee('Exportar setores CSV')->assertDontSee('Registros do período por setor');
        $service = app(ShiftReportService::class);
        $report = $service->forPeriod('2026-10', (string) $other->id, '', $data['professional']);
        $this->assertSame(5, $report['summary']['total']);
        $this->assertCount(0, $report['sectorIndicators']);
        $this->expectException(AuthorizationException::class);
        $service->sectorIndicators('2026-10', '', '', $data['professional']);
    }

    public function test_no_registered_sectors_has_clear_empty_state(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager, 'sector_id' => null, 'category_id' => null]);
        Livewire::actingAs($manager)->test(Reports::class, ['month' => '2026-10', 'tab' => 'coverage'])
            ->assertSee('Nenhum setor corresponde aos filtros.')->assertSee('0 setor(es)');
    }
}
