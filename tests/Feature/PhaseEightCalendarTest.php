<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\MonthlyCalendar;
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

    public function test_professional_cannot_access_the_manager_calendar(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->actingAs($professional)->get(route('manager.calendar'))->assertForbidden();
        Livewire::actingAs($professional)->test(MonthlyCalendar::class)->assertForbidden();
    }
}
