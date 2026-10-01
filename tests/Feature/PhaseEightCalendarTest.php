<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\MonthlyCalendar;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseEightCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_select_a_day_and_see_every_shift_and_professional(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::query()->create(['name' => 'Enfermagem']);
        $sector = Sector::query()->create(['name' => 'Emergência']);
        $ana = User::factory()->create([
            'name' => 'Ana Plantonista',
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $bruno = User::factory()->create([
            'name' => 'Bruno Plantonista',
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        foreach ([[$ana->id, '07:00', '13:00'], [$bruno->id, '13:00', '19:00'], [null, '19:00', '23:00']] as [$userId, $start, $end]) {
            Shift::query()->create([
                'date' => '2026-11-12',
                'start_time' => $start,
                'end_time' => $end,
                'sector_id' => $sector->id,
                'category_id' => $category->id,
                'user_id' => $userId,
                'status' => $userId ? ShiftStatus::Scheduled : ShiftStatus::Uncovered,
            ]);
        }

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', '2026-11')
            ->call('selectDate', '2026-11-12')
            ->assertSet('selectedDate', '2026-11-12')
            ->assertSee('Ana Plantonista')
            ->assertSee('Bruno Plantonista')
            ->assertSee('Plantão sem profissional')
            ->assertSee('3 plantão(ões) · 2 profissional(is)')
            ->assertSee('Emergência')
            ->assertSee('Enfermagem');
    }

    public function test_calendar_moves_between_months_and_rejects_invalid_or_outside_dates(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', '2026-11')
            ->assertSet('selectedDate', '2026-11-01')
            ->call('nextMonth')
            ->assertSet('month', '2026-12')
            ->assertSet('selectedDate', '2026-12-01')
            ->call('selectDate', '2026-02-31')
            ->assertSet('selectedDate', '2026-12-01')
            ->call('selectDate', '2027-01-01')
            ->assertSet('selectedDate', '2026-12-01')
            ->call('previousMonth')
            ->assertSet('month', '2026-11')
            ->assertSet('selectedDate', '2026-11-01');
    }

    public function test_professional_can_view_the_shared_calendar_without_management_actions(): void
    {
        $category = Category::query()->create(['name' => 'Enfermagem']);
        $sector = Sector::query()->create(['name' => 'Emergência']);
        $professional = User::factory()->create([
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $colleague = User::factory()->create([
            'name' => 'Colega Plantonista',
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        Shift::query()->create([
            'date' => '2026-11-12',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $colleague->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->actingAs($professional)->get(route('manager.calendar'))->assertForbidden();
        $this->actingAs($professional)
            ->get(route('professional.calendar', ['month' => '2026-11']))
            ->assertOk()
            ->assertSee('Calendário de plantões')
            ->assertSeeInOrder(['Minha escala', 'Calendário', 'Notificações', 'Ajuda e versões'])
            ->assertDontSee(route('manager.shifts'), false)
            ->assertDontSee('Gerenciar plantões');

        Livewire::actingAs($professional)
            ->test(MonthlyCalendar::class)
            ->set('month', '2026-11')
            ->call('selectDate', '2026-11-12')
            ->assertSee('Colega Plantonista')
            ->assertDontSee('Abrir escala');
    }

    public function test_manager_can_filter_calendar_by_sector(): void
    {
        [$manager, $emergency, $icu, $category] = $this->twoSectorSetup();

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', '2026-11')
            ->call('selectDate', '2026-11-12')
            ->assertSee('Ana da Emergência')
            ->assertSee('Bruno da UTI')
            ->set('sectorId', (string) $icu->id)
            ->assertSet('sectorId', (string) $icu->id)
            ->assertSee('Bruno da UTI')
            ->assertDontSee('Ana da Emergência')
            ->assertSee('1 plantão(ões) · 1 profissional(is)');
    }

    public function test_manager_can_filter_calendar_by_category(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::query()->create(['name' => 'Emergência']);
        $medicine = Category::query()->create(['name' => 'Medicina']);
        $nursing = Category::query()->create(['name' => 'Enfermagem']);
        $doctor = User::factory()->create([
            'name' => 'Médico Plantonista',
            'category_id' => $medicine->id,
            'sector_id' => $sector->id,
        ]);
        $nurse = User::factory()->create([
            'name' => 'Enfermeira Plantonista',
            'category_id' => $nursing->id,
            'sector_id' => $sector->id,
        ]);

        foreach ([[$doctor, $medicine], [$nurse, $nursing]] as [$professional, $category]) {
            Shift::query()->create([
                'date' => '2026-11-12',
                'start_time' => '07:00',
                'end_time' => '19:00',
                'sector_id' => $sector->id,
                'category_id' => $category->id,
                'user_id' => $professional->id,
                'status' => ShiftStatus::Scheduled,
            ]);
        }

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', '2026-11')
            ->call('selectDate', '2026-11-12')
            ->set('categoryId', (string) $nursing->id)
            ->assertSee('Enfermeira Plantonista')
            ->assertDontSee('Médico Plantonista')
            ->assertSee('1 plantão(ões) · 1 profissional(is)');
    }

    public function test_calendar_filters_can_be_combined_cleared_and_reject_unknown_ids(): void
    {
        [$manager, $emergency, $icu, $category] = $this->twoSectorSetup();

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', '2026-11')
            ->call('selectDate', '2026-11-12')
            ->set('sectorId', (string) $emergency->id)
            ->set('categoryId', (string) $category->id)
            ->assertSee('Ana da Emergência')
            ->assertDontSee('Bruno da UTI')
            ->assertSee('Limpar filtros')
            ->call('clearFilters')
            ->assertSet('sectorId', '')
            ->assertSet('categoryId', '')
            ->assertSee('Ana da Emergência')
            ->assertSee('Bruno da UTI')
            ->set('sectorId', '999999')
            ->assertSet('sectorId', '')
            ->assertSee('Ana da Emergência')
            ->assertSee('Bruno da UTI');
    }

    /** @return array{User, Sector, Sector, Category} */
    private function twoSectorSetup(): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::query()->create(['name' => 'Medicina']);
        $emergency = Sector::query()->create(['name' => 'Emergência']);
        $icu = Sector::query()->create(['name' => 'UTI']);
        $ana = User::factory()->create([
            'name' => 'Ana da Emergência',
            'category_id' => $category->id,
            'sector_id' => $emergency->id,
        ]);
        $bruno = User::factory()->create([
            'name' => 'Bruno da UTI',
            'category_id' => $category->id,
            'sector_id' => $icu->id,
        ]);

        foreach ([[$ana, $emergency], [$bruno, $icu]] as [$professional, $sector]) {
            Shift::query()->create([
                'date' => '2026-11-12',
                'start_time' => '07:00',
                'end_time' => '19:00',
                'sector_id' => $sector->id,
                'category_id' => $category->id,
                'user_id' => $professional->id,
                'status' => ShiftStatus::Scheduled,
            ]);
        }

        return [$manager, $emergency, $icu, $category];
    }
}
