<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapReversion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class RevertSwapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfMonth()->addDays(5));
    }

    public function test_manager_can_revert_an_approved_swap_and_quota_is_released(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();
        $shift = $this->shift($requester, $category, $sector, 10);
        $swap = $this->approvedSwap($manager, $shift, $requester, $substitute);

        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->call('requestRevert', $swap->id)
            ->assertSet('pendingRevertSwapId', $swap->id)
            ->assertSee('Revisar desfazimento do pedido')
            ->set('reversionReason', 'Correção administrativa solicitada pelos profissionais.')
            ->call('confirmRevert')
            ->assertHasNoErrors()
            ->assertSee('Troca desfeita. Os profissionais voltaram aos plantões originais');

        $this->assertSame(ShiftSwapStatus::Reverted, $swap->refresh()->status);
        $this->assertSame($manager->id, $swap->manager_id);
        $this->assertSame($requester->id, $shift->refresh()->user_id);
        $this->assertFalse($requester->refresh()->hasReachedSwapLimit(now()->month, now()->year));
        $this->assertFalse($substitute->refresh()->hasReachedSwapLimit(now()->month, now()->year));

        $this->assertContains('reverted', $requester->notifications->pluck('data.event')->all());
        $this->assertContains('reverted', $substitute->notifications->pluck('data.event')->all());
    }

    public function test_reverted_swap_releases_monthly_quota(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $swaps = [];

        foreach (range(10, 13) as $daysAhead) {
            $swaps[] = $this->approvedSwap($manager, $this->shift($requester, $category, $sector, $daysAhead), $requester, $substitute);
        }

        $this->assertTrue($requester->refresh()->hasReachedSwapLimit(now()->month, now()->year));

        app(RevertShiftSwapAction::class)->execute($manager, $swaps[0]->id, 'Correção administrativa.', app(ShiftSwapReversion::class)->inspect($swaps[0]->fresh())['fingerprint']);

        $this->assertFalse($requester->refresh()->hasReachedSwapLimit(now()->month, now()->year));
        $this->assertFalse($substitute->refresh()->hasReachedSwapLimit(now()->month, now()->year));
    }

    public function test_revert_is_blocked_when_shift_has_absence_pending_swap_or_is_past(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        // Ausência no plantão.
        $absenceShift = $this->shift($requester, $category, $sector, 10);
        $absenceSwap = $this->approvedSwap($manager, $absenceShift, $requester, $substitute);
        Absence::query()->create([
            'shift_id' => $absenceShift->id,
            'user_id' => $substitute->id,
            'reason' => 'Atestado médico apresentado.',
            'status' => AbsenceStatus::Pending,
        ]);

        // Nova troca pendente sobre o plantão.
        $pendingShift = $this->shift($requester, $category, $sector, 11);
        $pendingSwap = $this->approvedSwap($manager, $pendingShift, $requester, $substitute);
        ShiftSwap::query()->create([
            'shift_id' => $pendingShift->id,
            'requester_id' => $substitute->id,
            'substitute_id' => $requester->id,
            'status' => ShiftSwapStatus::PendingProfessional,
        ]);

        foreach ([$absenceSwap, $pendingSwap] as $swap) {
            $this->assertThrows(
                fn () => app(RevertShiftSwapAction::class)->execute($manager, $swap->id, 'Correção administrativa.', app(ShiftSwapReversion::class)->inspect($swap->fresh())['fingerprint']),
                ValidationException::class,
            );
            $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
        }
    }

    public function test_revert_is_blocked_for_past_shifts_and_non_managers(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        $pastShift = $this->shift($requester, $category, $sector, -5);
        $pastSwap = ShiftSwap::factory()->approved()->create([
            'shift_id' => $pastShift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
        ]);
        $pastShift->update(['user_id' => $substitute->id]);

        $this->assertThrows(
            fn () => app(RevertShiftSwapAction::class)->execute($manager, $pastSwap->id, 'Correção administrativa.', app(ShiftSwapReversion::class)->inspect($pastSwap->fresh())['fingerprint']),
            ValidationException::class,
        );

        $futureShift = $this->shift($requester, $category, $sector, 10);
        $futureSwap = $this->approvedSwap($manager, $futureShift, $requester, $substitute);

        $this->assertThrows(
            fn () => app(RevertShiftSwapAction::class)->execute($requester, $futureSwap->id),
            AuthorizationException::class,
        );

        $this->assertSame(ShiftSwapStatus::Approved, $futureSwap->refresh()->status);
        $this->assertSame($substitute->id, $futureShift->refresh()->user_id);
    }

    /** @return array{User, User, User, Category, Sector} */
    private function team(): array
    {
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
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

    private function shift(User $professional, Category $category, Sector $sector, int $daysAhead): Shift
    {
        return Shift::factory()->create([
            'date' => today()->addDays($daysAhead),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);
    }

    private function approvedSwap(User $manager, Shift $shift, User $requester, User $substitute): ShiftSwap
    {
        $swap = ShiftSwap::factory()->pendingManager()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
        ]);

        return app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
    }
}
