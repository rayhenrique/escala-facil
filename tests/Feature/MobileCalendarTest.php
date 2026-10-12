<?php

namespace Tests\Feature;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\MonthlyCalendar;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\OperationalNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class MobileCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function professional(): User
    {
        return User::factory()->create(['sector_id' => Sector::factory(), 'category_id' => Category::factory()]);
    }

    private function shift(User $user, array $attributes = []): Shift
    {
        return Shift::factory()->create($attributes + ['user_id' => $user->id, 'sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
    }

    public function test_week_crosses_month_and_keeps_night_and_24_hour_labels(): void
    {
        $user = $this->professional();
        $night = $this->shift($user, ['date' => '2026-11-30', 'start_time' => '19:00', 'end_time' => '07:00']);
        $long = $this->shift($user, ['date' => '2026-12-01', 'start_time' => '07:00', 'end_time' => '07:00', 'end_date' => '2026-12-02']);
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->call('selectAgendaDate', '2026-11-30')
            ->assertViewHas('agendaStart', fn ($start) => $start->toDateString() === '2026-11-29')
            ->assertViewHas('agendaEnd', fn ($end) => $end->toDateString() === '2026-12-05')
            ->assertViewHas('agendaShifts', fn ($shifts) => $shifts->pluck('id')->all() === [$night->id, $long->id])
            ->assertSee('dia seguinte, 01/12/2026')->assertSee('12h')->assertSee('dia seguinte, 02/12/2026')->assertSee('24h')
            ->call('nextAgendaPeriod')->assertSet('month', '2026-12')->assertSet('selectedDate', '2026-12-07');
    }

    public function test_shared_url_keeps_week_across_year_change(): void
    {
        $user = $this->professional();
        $first = $this->shift($user, ['date' => '2026-12-31', 'start_time' => '19:00', 'end_time' => '07:00']);
        $second = $this->shift($user, ['date' => '2027-01-01', 'start_time' => '07:00', 'end_time' => '07:00', 'end_date' => '2027-01-02']);
        $component = Livewire::withQueryParams(['month' => '2026-12', 'selectedDate' => '2026-12-31', 'agendaView' => 'week'])
            ->actingAs($user)->test(MonthlyCalendar::class)->assertSet('selectedDate', '2026-12-31')
            ->assertViewHas('agendaShifts', fn ($shifts) => $shifts->pluck('id')->all() === [$first->id, $second->id])
            ->assertSee('dia seguinte, 01/01/2027')->assertSee('dia seguinte, 02/01/2027');
        $this->assertSame('week', $component->instance()->navigationContext()['agendaView']);
    }

    public function test_period_navigation_preserves_filters_and_handles_month_end_without_overflow(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $user = $this->professional();
        Livewire::actingAs($manager)->test(MonthlyCalendar::class)->set('sectorId', (string) $user->sector_id)
            ->set('categoryId', (string) $user->category_id)->call('selectAgendaDate', '2026-10-31')
            ->set('agendaView', 'month')->call('nextAgendaPeriod')->assertSet('selectedDate', '2026-11-30')
            ->assertSet('sectorId', (string) $user->sector_id)->assertSet('categoryId', (string) $user->category_id)
            ->set('agendaView', 'list')->assertViewHas('agendaEnd', fn ($end) => $end->toDateString() === '2026-12-29')
            ->call('previousAgendaPeriod')->assertSet('selectedDate', '2026-10-31');
    }

    public function test_invalid_modes_dates_and_context_are_normalized(): void
    {
        Livewire::actingAs($this->professional())->test(MonthlyCalendar::class)->set('agendaView', 'invalid')
            ->assertSet('agendaView', 'week')->call('selectAgendaDate', '2026-02-31')->assertSet('selectedDate', today()->toDateString());
        $this->assertSame(['agendaView' => 'list', 'agendaPage' => '2'], OperationalNavigation::clean('calendar', ['agendaView' => 'list', 'agendaPage' => 2]));
        $this->assertSame([], OperationalNavigation::clean('calendar', ['agendaView' => 'invalid', 'agendaPage' => -1]));
    }

    public function test_professional_cannot_read_other_sector_details_or_acquire_management_actions(): void
    {
        $user = $this->professional();
        $other = $this->shift($this->professional());
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->set('sectorId', (string) $other->sector_id)
            ->assertSet('sectorId', (string) $user->sector_id)->call('openCalendarDetail', $other->id)->assertNotFound();
        $own = $this->shift($user);
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->call('openCalendarDetail', $own->id)
            ->assertSee('Ver minha escala')->assertDontSee('Abrir escala')->call('reallocateCalendarDetail')->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(MonthlyCalendar::class)->assertViewHas('agendaShifts', fn ($shifts) => $shifts->total() === 0);
    }

    public function test_detail_identifier_is_locked(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs($this->professional())->test(MonthlyCalendar::class)->set('calendarDetailId', 1);
    }

    public function test_opening_and_closing_details_performs_no_history_writes(): void
    {
        $user = $this->professional();
        $shift = $this->shift($user);
        DB::enableQueryLog();
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->call('openCalendarDetail', $shift->id)
            ->assertSet('calendarDetailId', $shift->id)->call('closeCalendarDetail')->assertSet('calendarDetailId', null);
        $this->assertCount(0, collect(DB::getQueryLog())->filter(fn ($query) => preg_match('/^(insert|update|delete)/i', $query['query'])));
        DB::disableQueryLog();
    }

    public function test_agenda_is_paginated_by_database_and_resets_on_period_or_filter_change(): void
    {
        $user = $this->professional();
        Shift::factory()->count(27)->create(['date' => '2026-11-01', 'user_id' => $user->id, 'sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
        $this->shift($user, ['date' => '2027-01-01']);
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->call('selectAgendaDate', '2026-11-01')
            ->assertViewHas('agendaShifts', fn ($shifts) => $shifts->total() === 27 && $shifts->count() === 25 && $shifts->every(fn ($shift) => $shift->relationLoaded('user') && $shift->relationLoaded('sector') && $shift->relationLoaded('category')))
            ->call('setPage', 2, 'agendaPage')->assertViewHas('agendaShifts', fn ($shifts) => $shifts->count() === 2)
            ->set('agendaView', 'month')->assertViewHas('agendaShifts', fn ($shifts) => $shifts->currentPage() === 1)
            ->call('nextAgendaPeriod')->assertViewHas('agendaShifts', fn ($shifts) => $shifts->total() === 0);
    }

    public function test_approved_mutual_swaps_and_uncovered_shifts_are_identified_without_changing_assignments(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $user = $this->professional();
        $first = $this->shift($user, ['date' => '2026-11-01']);
        $second = $this->shift($user, ['date' => '2026-11-02']);
        $swap = ShiftSwap::factory()->create(['shift_id' => $first->id, 'substitute_shift_id' => $second->id,
            'requester_id' => $user->id, 'status' => ShiftSwapStatus::Approved]);
        $uncovered = Shift::factory()->uncovered()->create(['date' => '2026-11-03', 'sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
        Livewire::actingAs($manager)->test(MonthlyCalendar::class)->call('selectAgendaDate', '2026-11-01')
            ->assertSee('Troca realizada')->assertSee('Precisa de cobertura')
            ->assertViewHas('swappedShiftIds', fn ($ids) => in_array($first->id, $ids, true) && in_array($second->id, $ids, true))
            ->call('openCalendarDetail', $uncovered->id)->assertSee('Preencher plantão');
        $this->assertSame(ShiftSwapStatus::Approved, $swap->fresh()->status);
        $this->assertNull($uncovered->fresh()->user_id);
    }

    public function test_next_shift_navigation_opens_its_page_among_colleagues(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 30)->setTime(1, 0));
        $user = $this->professional();
        Shift::factory()->count(27)->create(['date' => today()->toDateString(), 'sector_id' => $user->sector_id, 'category_id' => $user->category_id, 'start_time' => '19:00', 'end_time' => '07:00']);
        $own = $this->shift($user, ['date' => today()->toDateString(), 'start_time' => '19:00', 'end_time' => '07:00']);
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->call('goToNextShift')
            ->assertViewHas('agendaShifts', fn ($shifts) => $shifts->currentPage() === 2 && $shifts->pluck('id')->contains($own->id))
            ->assertSee('Seu próximo plantão')->assertDispatched('calendar-shift-located', target: 'calendar-agenda-shift-'.$own->id);
    }

    public function test_next_professional_shift_ignores_colleagues_and_respects_category(): void
    {
        $this->travelTo(now()->setTime(6, 0));
        $user = $this->professional();
        $own = $this->shift($user, ['date' => today()->toDateString(), 'start_time' => '09:00']);
        $this->shift($user, ['user_id' => User::factory()->create()->id, 'date' => today()->toDateString(), 'start_time' => '07:00']);
        Livewire::actingAs($user)->test(MonthlyCalendar::class)->call('selectAgendaDate', '2026-09-01')->assertSee('Ir ao próximo plantão')
            ->call('goToNextShift')->assertSet('selectedDate', today()->toDateString())->assertViewHas('nextUpcomingId', $own->id)->assertSee('Seu próximo plantão');
    }
}
