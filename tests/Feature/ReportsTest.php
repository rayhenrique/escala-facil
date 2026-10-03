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

    public function test_professional_cannot_open_manager_reports_routes(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)->get(route('manager.reports'))->assertForbidden();
        $this->actingAs($professional)->get(route('manager.reports.pdf'))->assertForbidden();
        $this->actingAs($professional)->get(route('manager.reports.wall'))->assertForbidden();
    }

    public function test_professional_can_view_shared_reports_and_download_pdfs(): void
    {
        [, $ana, , $sector, $category] = $this->team();

        $professional = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        Shift::factory()->create([
            'date' => '2026-11-12',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($professional)
            ->get(route('professional.reports', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('Relatórios')
            ->assertSeeInOrder(['Minha escala', 'Calendário', 'Relatórios', 'Notificações', 'Ajuda e versões'])
            ->assertSee('Ana Plantonista');

        $this->actingAs($professional)->get(route('professional.reports.pdf', ['month' => '2026-11']))->assertOk();
        $this->actingAs($professional)->get(route('professional.reports.wall', ['month' => '2026-11']))->assertOk();

        Livewire::actingAs($professional)
            ->test(Reports::class)
            ->set('month', '2026-11')
            ->assertSee('1 plantão(ões) no período selecionado.');
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

    public function test_professional_only_sees_own_swaps_in_reports_while_manager_sees_all_swaps(): void
    {
        [$manager, $ana, $bruno, $sector, $category] = $this->team();
        $bruno->update(['name' => 'Bruno Santos']);
        $carlos = User::factory()->create(['name' => 'Carlos Silva', 'category_id' => $category->id, 'sector_id' => $sector->id]);
        $daniel = User::factory()->create(['name' => 'Daniel Souza', 'category_id' => $category->id, 'sector_id' => $sector->id]);

        $shiftAna = Shift::factory()->create([
            'date' => '2026-11-10',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);
        $shiftCarlos = Shift::factory()->create([
            'date' => '2026-11-15',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $carlos->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        // Swap 1: between Ana and Bruno
        $swapAna = ShiftSwap::factory()->create([
            'shift_id' => $shiftAna->id,
            'requester_id' => $ana->id,
            'substitute_id' => $bruno->id,
            'created_at' => '2026-11-05 10:00:00',
        ]);

        // Swap 2: between Carlos and Daniel
        $swapCarlos = ShiftSwap::factory()->create([
            'shift_id' => $shiftCarlos->id,
            'requester_id' => $carlos->id,
            'substitute_id' => $daniel->id,
            'created_at' => '2026-11-06 14:00:00',
        ]);

        // Professional (Ana) accesses reports: sees Swap 1, but DOES NOT see Swap 2
        Livewire::actingAs($ana)
            ->test(Reports::class)
            ->set('month', '2026-11')
            ->assertSee('Minhas trocas no mês')
            ->assertSee('Ana Plantonista → Bruno Santos')
            ->assertDontSee('Carlos Silva → Daniel Souza');

        // Manager accesses reports: sees BOTH swaps
        Livewire::actingAs($manager)
            ->test(Reports::class)
            ->set('month', '2026-11')
            ->assertSee('Trocas criadas no mês')
            ->assertSee('Ana Plantonista → Bruno Santos')
            ->assertSee('Carlos Silva → Daniel Souza');
    }

    public function test_professional_only_sees_own_sector_in_reports_and_pdf_exports(): void
    {
        [$manager, $ana, $bruno, $emergency, $icu, $category] = $this->twoSectorTeam();

        Shift::factory()->create([
            'date' => '2026-11-12',
            'sector_id' => $emergency->id,
            'category_id' => $category->id,
            'user_id' => $ana->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        Shift::factory()->create([
            'date' => '2026-11-12',
            'sector_id' => $icu->id,
            'category_id' => $category->id,
            'user_id' => $bruno->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        Livewire::actingAs($ana)
            ->test(Reports::class)
            ->set('month', '2026-11')
            ->assertSet('sectorId', (string) $emergency->id)
            ->assertSee('1 plantão(ões) no período selecionado.')
            ->assertSee('Ana Plantonista')
            ->assertDontSee('Bruno Plantonista')
            ->assertSee('Emergência')
            ->assertDontSee('UTI')
            ->assertSeeHtml('value="Emergência"')
            ->assertSeeHtml('disabled')
            ->set('sectorId', (string) $icu->id)
            ->assertSet('sectorId', (string) $emergency->id)
            ->assertSee('Ana Plantonista')
            ->assertDontSee('Bruno Plantonista')
            ->call('clearFilters')
            ->assertSet('sectorId', (string) $emergency->id)
            ->assertSee('Ana Plantonista')
            ->assertDontSee('Bruno Plantonista');

        $pdfResponse = $this->actingAs($ana)->get(route('professional.reports.pdf', [
            'month' => '2026-11',
            'sector_id' => $icu->id,
        ]));
        $pdfResponse->assertOk();

        $wallResponse = $this->actingAs($ana)->get(route('professional.reports.wall', [
            'month' => '2026-11',
            'sector_id' => $icu->id,
        ]));
        $wallResponse->assertOk();

        $service = app(ShiftReportService::class);
        $anaReport = $service->forPeriod('2026-11', (string) $icu->id, '', $ana);
        $this->assertSame(1, $anaReport['summary']['total']);
        $this->assertSame('Emergência', $anaReport['bySector']->first()['name']);
        $this->assertCount(1, $anaReport['bySector']);

        $anaWall = $service->wallRoster('2026-11', (string) $icu->id, '', $ana);
        $this->assertSame(1, $anaWall['total']);
        $this->assertSame('Emergência', $anaWall['days']['2026-11-12']->first()->sector->name);
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
