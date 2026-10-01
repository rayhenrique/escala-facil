<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Reports;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_sees_monthly_summary_with_professional_breakdown(): void
    {
        [$manager, $ana, , $sector, $category] = $this->team();

        Shift::factory()->create([
            'date' => '2026-11-12',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);
        Shift::factory()->uncovered()->create([
            'date' => '2026-11-13',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'status' => ShiftStatus::Uncovered,
        ]);

        $this->actingAs($manager)
            ->get(route('manager.reports', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('Relatórios')
            ->assertSeeInOrder(['Visão geral', 'Calendário', 'Escalas', 'Trocas', 'Relatórios']);

        Livewire::actingAs($manager)
            ->test(Reports::class)
            ->set('month', '2026-11')
            ->assertSee('2 plantão(ões) no período selecionado.')
            ->assertSee('50%')
            ->assertSee('Ana Plantonista')
            ->assertSee('1 plantão(ões) · 0 de 4 trocas · 0 ausência(s)');
    }

    public function test_sector_filter_narrows_report_numbers(): void
    {
        [$manager, $ana, $bruno, $emergency, $icu, $category] = $this->twoSectorTeam();

        foreach ([[$ana, $emergency], [$bruno, $icu]] as [$professional, $sector]) {
            Shift::factory()->create([
                'date' => '2026-11-12',
                'sector_id' => $sector->id,
                'category_id' => $category->id,
                'user_id' => $professional->id,
                'status' => ShiftStatus::Scheduled,
            ]);
        }

        Livewire::actingAs($manager)
            ->test(Reports::class)
            ->set('month', '2026-11')
            ->assertSee('Ana Plantonista')
            ->assertSee('Bruno Plantonista')
            ->set('sectorId', (string) $icu->id)
            ->assertSee('1 plantão(ões) no período selecionado.')
            ->assertSee('Bruno Plantonista')
            ->assertDontSee('Ana Plantonista')
            ->assertSee('Limpar filtros')
            ->call('clearFilters')
            ->assertSet('sectorId', '')
            ->assertSee('Ana Plantonista');
    }

    public function test_invalid_month_is_normalized_before_queries_run(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::actingAs($manager)
            ->test(Reports::class)
            ->set('month', '2026-99')
            ->assertSet('month', now()->format('Y-m'))
            ->assertHasNoErrors();
    }

    public function test_professional_cannot_open_reports_or_download_pdf(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)->get(route('manager.reports'))->assertForbidden();
        $this->actingAs($professional)->get(route('manager.reports.pdf'))->assertForbidden();
    }

    public function test_manager_can_download_report_pdf(): void
    {
        [$manager, $ana, , $sector, $category] = $this->team();

        Shift::factory()->create([
            'date' => '2026-11-12',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $response = $this->actingAs($manager)->get(route('manager.reports.pdf', ['month' => '2026-11']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_manager_can_download_wall_roster_pdf(): void
    {
        [$manager, $ana, , $sector, $category] = $this->team();

        Shift::factory()->create([
            'date' => '2026-11-12',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $response = $this->actingAs($manager)->get(route('manager.reports.wall', ['month' => '2026-11']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_professional_cannot_download_wall_roster_pdf(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)->get(route('manager.reports.wall'))->assertForbidden();
    }

    public function test_wall_roster_marks_shifts_received_through_approved_swaps(): void
    {
        [$manager, $ana, $bruno, $sector, $category] = $this->team();

        $shift = Shift::factory()->create([
            'date' => '2026-11-12',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);
        $otherMonth = Shift::factory()->create([
            'date' => '2026-12-12',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $swap = ShiftSwap::factory()->pendingManager()->create([
            'shift_id' => $shift->id,
            'requester_id' => $ana->id,
            'substitute_id' => $bruno->id,
        ]);
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);

        $roster = app(ShiftReportService::class)->wallRoster('2026-11');

        $this->assertContains($shift->id, $roster['swappedShiftIds']);
        $this->assertNotContains($otherMonth->id, $roster['swappedShiftIds']);
        $this->assertSame(1, $roster['total']);
    }

    /** @return array{User, User, User, Sector, Category} */
    private function team(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
        $ana = User::factory()->create([
            'name' => 'Ana Plantonista',
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $other = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        return [$manager, $ana, $other, $sector, $category];
    }

    /** @return array{User, User, User, Sector, Sector, Category} */
    private function twoSectorTeam(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::factory()->create();
        $emergency = Sector::factory()->create(['name' => 'Emergência']);
        $icu = Sector::factory()->create(['name' => 'UTI']);
        $ana = User::factory()->create([
            'name' => 'Ana Plantonista',
            'category_id' => $category->id,
            'sector_id' => $emergency->id,
        ]);
        $bruno = User::factory()->create([
            'name' => 'Bruno Plantonista',
            'category_id' => $category->id,
            'sector_id' => $icu->id,
        ]);

        return [$manager, $ana, $bruno, $emergency, $icu, $category];
    }
}
