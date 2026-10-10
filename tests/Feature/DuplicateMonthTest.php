<?php

namespace Tests\Feature;

use App\Actions\Shifts\DuplicateMonthAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\ShiftManager;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class DuplicateMonthTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_maps_same_day_and_flags_nonexistent_dates(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $professional);
        $this->makeShift('2027-01-31', '07:00', '19:00', $category, $sector, $professional);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'day');

        $this->assertSame(['total' => 2, 'valid' => 1, 'adjusted' => 0, 'blocked' => 1], $preview['totals']);
        $this->assertSame('2027-02-05', $preview['items'][0]['date']);
        $this->assertSame('valid', $preview['items'][0]['status']);
        $this->assertNull($preview['items'][1]['date']);
        $this->assertSame('blocked', $preview['items'][1]['status']);
        $this->assertStringContainsString('inexistente', mb_strtolower($preview['items'][1]['reasons'][0]));
    }

    public function test_weekday_mode_maps_nth_weekday(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        // 12/01/2027 é a 2ª terça-feira de janeiro; 09/02/2027 é a 2ª de fevereiro.
        $this->makeShift('2027-01-12', '07:00', '19:00', $category, $sector, $professional);
        // 31/01/2027 é o 5º domingo de janeiro; fevereiro de 2027 tem só 4 domingos.
        $this->makeShift('2027-01-31', '07:00', '19:00', $category, $sector, $professional);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'weekday');

        $this->assertSame('2027-02-09', $preview['items'][0]['date']);
        $this->assertSame('valid', $preview['items'][0]['status']);
        $this->assertNull($preview['items'][1]['date']);
        $this->assertSame('blocked', $preview['items'][1]['status']);
    }

    public function test_existing_identical_shifts_are_never_overwritten(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $source = $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $professional);
        $existing = $this->makeShift('2027-02-05', '07:00', '19:00', $category, $sector, $professional);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'day');
        $this->assertSame('blocked', $preview['items'][0]['status']);

        $result = app(DuplicateMonthAction::class)->execute($manager, '2027-01', '2027-02', 'day', [$source->id]);

        $this->assertSame(['created' => 0, 'ignored' => 1, 'adjusted' => 0], $result);
        $this->assertSame([$existing->id], Shift::query()->whereDate('date', '2027-02-05')->pluck('id')->all());
    }

    public function test_time_conflicts_are_blocked_but_boundary_touch_is_allowed(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $this->makeShift('2027-02-05', '10:00', '14:00', $category, $sector, $professional);

        $overlap = $this->makeShift('2027-01-05', '08:00', '12:00', $category, $sector, $professional);
        $touch = $this->makeShift('2027-01-06', '14:00', '19:00', $category, $sector, $professional);
        $overnight = $this->makeShift('2027-01-07', '19:00', '07:00', $category, $sector, $professional);
        $this->makeShift('2027-02-07', '22:00', '06:00', $category, $sector, $professional);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'day');
        $bySource = collect($preview['items'])->keyBy('source_id');

        $this->assertSame('blocked', $bySource[$overlap->id]['status']);
        $this->assertStringContainsString('Conflito', $bySource[$overlap->id]['reasons'][0]);
        $this->assertSame('valid', $bySource[$touch->id]['status']);
        $this->assertSame('blocked', $bySource[$overnight->id]['status']);
    }

    public function test_incompatible_professionals_are_blocked(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];
        $otherSector = Sector::query()->create(['name' => 'UTI']);

        $managerUser = User::factory()->create(['role' => UserRole::Manager]);
        $foreignShift = $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $managerUser);

        $movedShift = $this->makeShift('2027-01-06', '07:00', '19:00', $category, $sector, $professional);
        $professional->update(['sector_id' => $otherSector->id]);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'day');
        $bySource = collect($preview['items'])->keyBy('source_id');

        $this->assertSame('blocked', $bySource[$foreignShift->id]['status']);
        $this->assertSame('blocked', $bySource[$movedShift->id]['status']);
    }

    public function test_swaps_absences_and_notifications_are_not_copied(): void
    {
        [$manager, $requester, $substitute] = [$this->manager(), $this->professional(), $this->professional()];
        [$category, $sector] = [$this->category(), $this->sector()];

        $shift = Shift::query()->create([
            'date' => '2027-01-05',
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        Absence::query()->create([
            'shift_id' => $shift->id,
            'user_id' => $requester->id,
            'reason' => 'Atestado médico apresentado.',
            'status' => AbsenceStatus::Pending,
        ]);

        $notificationsBefore = (int) $this->notificationsCount();

        $result = app(DuplicateMonthAction::class)->execute($manager, '2027-01', '2027-02', 'day', [$shift->id]);

        $this->assertSame(1, $result['created']);
        $copied = Shift::query()->whereDate('date', '2027-02-05')->sole();
        $this->assertSame(0, ShiftSwap::query()->where('shift_id', $copied->id)->count());
        $this->assertSame(0, Absence::query()->where('shift_id', $copied->id)->count());
        $this->assertSame($notificationsBefore, $this->notificationsCount());
    }

    public function test_past_target_dates_are_blocked(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $source = $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $professional);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2026-01', 'day');
        $this->assertSame('blocked', $preview['items'][0]['status']);

        $result = app(DuplicateMonthAction::class)->execute($manager, '2027-01', '2026-01', 'day', [$source->id]);
        $this->assertSame(['created' => 0, 'ignored' => 1, 'adjusted' => 0], $result);
    }

    public function test_absence_reported_shifts_are_created_as_uncovered(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $source = $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $professional, ShiftStatus::AbsenceReported);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'day');
        $this->assertSame('adjusted', $preview['items'][0]['status']);

        $result = app(DuplicateMonthAction::class)->execute($manager, '2027-01', '2027-02', 'day', [$source->id]);

        $this->assertSame(['created' => 1, 'ignored' => 0, 'adjusted' => 1], $result);
        $this->assertSame(
            ShiftStatus::Uncovered,
            Shift::query()->whereDate('date', '2027-02-05')->sole()->status
        );
    }

    public function test_same_month_and_invalid_months_are_rejected(): void
    {
        [$manager] = $this->team();

        $this->assertThrows(
            fn () => app(DuplicateMonthAction::class)->preview($manager, '2027-02', '2027-02', 'day'),
            ValidationException::class,
        );
        $this->assertThrows(
            fn () => app(DuplicateMonthAction::class)->preview($manager, 'fevereiro', '2027-03', 'day'),
            ValidationException::class,
        );
    }

    public function test_professional_cannot_preview_or_confirm(): void
    {
        [, $professional] = $this->team();

        $this->actingAs($professional)->get(route('manager.shifts'))->assertForbidden();

        $this->assertThrows(
            fn () => app(DuplicateMonthAction::class)->preview($professional, '2027-01', '2027-02', 'day'),
            AuthorizationException::class,
        );
        $this->assertThrows(
            fn () => app(DuplicateMonthAction::class)->execute($professional, '2027-01', '2027-02', 'day', [1]),
            AuthorizationException::class,
        );
    }

    public function test_concurrent_creation_is_counted_as_ignored(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $first = $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $professional);
        $second = $this->makeShift('2027-01-06', '07:00', '19:00', $category, $sector, $professional);

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-01', '2027-02', 'day');
        $this->assertSame(2, $preview['totals']['valid']);

        // Outro gerente cria o mesmo plantão entre a prévia e a confirmação.
        $this->makeShift('2027-02-05', '07:00', '19:00', $category, $sector, $professional);

        $result = app(DuplicateMonthAction::class)->execute($manager, '2027-01', '2027-02', 'day', [$first->id, $second->id]);

        $this->assertSame(['created' => 1, 'ignored' => 1, 'adjusted' => 0], $result);
        $this->assertSame(1, Shift::query()->whereDate('date', '2027-02-05')->count());
    }

    public function test_empty_source_and_copy_modal_flow_report_results(): void
    {
        [$manager, $professional] = $this->team();
        [$category, $sector] = [$this->category(), $this->sector()];

        $preview = app(DuplicateMonthAction::class)->preview($manager, '2027-03', '2027-04', 'day');
        $this->assertSame(['total' => 0, 'valid' => 0, 'adjusted' => 0, 'blocked' => 0], $preview['totals']);

        $source = $this->makeShift('2027-01-05', '07:00', '19:00', $category, $sector, $professional);

        Livewire::actingAs($manager)
            ->test(ShiftManager::class)
            ->call('openCopyModal')
            ->assertSet('copySourceMonth', now()->format('Y-m'))
            ->set('copySourceMonth', '2027-01')
            ->set('copyTargetMonth', '2027-02')
            ->call('generateCopyPreview')
            ->assertHasNoErrors()
            ->assertSee('Prévia: 1 plantão(ões)')
            ->assertSee('Confirmar cópia (1)')
            ->call('confirmCopy')
            ->assertSee('Cópia concluída: 1 plantão(ões) criado(s) e 0 ignorado(s).')
            ->assertSet('month', '2027-02');

        $this->assertTrue(Shift::query()->whereDate('date', '2027-02-05')->exists());
        $this->assertSame($source->id, $source->refresh()->id);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => UserRole::Manager]);
    }

    private function professional(): User
    {
        $category = Category::query()->firstOrCreate(['name' => 'Médico Clínico']);
        $sector = Sector::query()->firstOrCreate(['name' => 'Emergência']);

        return User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
    }

    /** @return array{User, User} */
    private function team(): array
    {
        return [$this->manager(), $this->professional()];
    }

    private function category(): Category
    {
        return Category::query()->firstOrCreate(['name' => 'Médico Clínico']);
    }

    private function sector(): Sector
    {
        return Sector::query()->firstOrCreate(['name' => 'Emergência']);
    }

    private function makeShift(
        string $date,
        string $start,
        string $end,
        Category $category,
        Sector $sector,
        ?User $professional,
        ShiftStatus $status = ShiftStatus::Scheduled,
    ): Shift {
        return Shift::query()->create([
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional?->id,
            'status' => $status,
        ]);
    }

    private function notificationsCount(): int
    {
        return (int) DB::table('notifications')->count();
    }
}
