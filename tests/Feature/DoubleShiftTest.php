<?php

namespace Tests\Feature;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DoubleShiftTest extends TestCase
{
    use RefreshDatabase;

    public function test_accept_review_warns_about_back_to_back_shift(): void
    {
        [, $requester, $substitute, $category, $sector] = $this->team();
        $shift = $this->shift($requester, $category, $sector, '2026-11-12', '07:00', '19:00');
        $this->shift($substitute, $category, $sector, '2026-11-12', '19:00', '07:00');
        $swap = $this->makeSwap($shift, $requester, $substitute);

        Livewire::actingAs($substitute)
            ->test(MySchedule::class)
            ->call('requestAcceptSwap', $swap->id)
            ->assertSet('pendingConfirmation', 'accept_swap')
            ->assertSee('Dobra:')
            ->assertSee('12/11/2026, das 19:00 às 07:00');
    }

    public function test_accept_review_has_no_warning_without_adjacent_shift(): void
    {
        [, $requester, $substitute, $category, $sector] = $this->team();
        $shift = $this->shift($requester, $category, $sector, '2026-11-12', '07:00', '19:00');
        $swap = $this->makeSwap($shift, $requester, $substitute);

        Livewire::actingAs($substitute)
            ->test(MySchedule::class)
            ->call('requestAcceptSwap', $swap->id)
            ->assertSet('pendingConfirmation', 'accept_swap')
            ->assertDontSee('emenda (dobra)');
    }

    public function test_approval_queue_flags_double_shift_and_still_allows_approval(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();
        $shift = $this->shift($requester, $category, $sector, '2026-11-12', '07:00', '19:00');
        $this->shift($substitute, $category, $sector, '2026-11-12', '19:00', '07:00');
        $swap = $this->makeSwap($shift, $requester, $substitute, ShiftSwapStatus::PendingManager);

        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->assertSee('Dobra')
            ->assertSee('emenda com o plantão do substituto')
            ->call('requestDecision', $swap->id, 'approve')
            ->assertSee('Aprove somente se a dobra for autorizada')
            ->call('confirmDecision')
            ->assertHasNoErrors();

        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
        $this->assertSame($substitute->id, $shift->refresh()->user_id);
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

    private function shift(User $professional, Category $category, Sector $sector, string $date, string $start, string $end): Shift
    {
        return Shift::factory()->create([
            'date' => $date,
            'start_time' => $start,
            'end_time' => $end,
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);
    }

    private function makeSwap(Shift $shift, User $requester, User $substitute, ShiftSwapStatus $status = ShiftSwapStatus::PendingProfessional): ShiftSwap
    {
        return ShiftSwap::factory()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => $status,
        ]);
    }
}
