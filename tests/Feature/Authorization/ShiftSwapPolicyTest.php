<?php

namespace Tests\Feature\Authorization;

use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ShiftSwapPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_the_compatible_named_substitute_can_accept_a_pending_swap(): void
    {
        [$requester, $substitute, $shift] = $this->assignment();
        $other = User::factory()->create([
            'category_id' => $requester->category_id,
            'sector_id' => $requester->sector_id,
        ]);
        $swap = $this->makeSwap($shift, $requester, $substitute);

        $this->assertTrue(Gate::forUser($substitute)->allows('accept', $swap));
        $this->assertFalse(Gate::forUser($other)->allows('accept', $swap));

        $swap->update(['status' => ShiftSwapStatus::PendingManager]);
        $this->assertFalse(Gate::forUser($substitute)->allows('accept', $swap->refresh()));
    }

    public function test_substitute_with_a_different_assignment_or_full_quota_cannot_accept(): void
    {
        [$requester, $substitute, $shift] = $this->assignment();
        $swap = $this->makeSwap($shift, $requester, $substitute);
        $differentSector = Sector::query()->create(['name' => 'UTI']);

        $substitute->update(['sector_id' => $differentSector->id]);
        $this->assertFalse(Gate::forUser($substitute)->allows('accept', $swap));

        $substitute->update(['sector_id' => $requester->sector_id]);
        foreach (range(1, User::MONTHLY_SWAP_LIMIT) as $index) {
            $countedShift = Shift::query()->create([
                'date' => now()->addDays($index)->toDateString(),
                'start_time' => '07:00',
                'end_time' => '19:00',
                'sector_id' => $requester->sector_id,
                'category_id' => $requester->category_id,
                'user_id' => $requester->id,
                'status' => ShiftStatus::Scheduled,
            ]);
            ShiftSwap::query()->create([
                'shift_id' => $countedShift->id,
                'requester_id' => $requester->id,
                'substitute_id' => $substitute->id,
                'status' => ShiftSwapStatus::Approved,
            ]);
        }

        $this->assertFalse(Gate::forUser($substitute)->allows('accept', $swap));
        $this->assertFalse(Gate::forUser($substitute)->allows('create', ShiftSwap::class));
    }

    public function test_only_a_manager_can_decide_a_swap_awaiting_management(): void
    {
        [$requester, $substitute, $shift] = $this->assignment();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $swap = $this->makeSwap($shift, $requester, $substitute, ShiftSwapStatus::PendingManager);

        $this->assertTrue(Gate::forUser($manager)->allows('approve', $swap));
        $this->assertTrue(Gate::forUser($manager)->allows('reject', $swap));
        $this->assertFalse(Gate::forUser($substitute)->allows('approve', $swap));

        $swap->update(['status' => ShiftSwapStatus::Approved]);
        $this->assertFalse(Gate::forUser($manager)->allows('approve', $swap->refresh()));
    }

    /** @return array{User, User, Shift} */
    private function assignment(): array
    {
        $category = Category::query()->create(['name' => 'Enfermagem']);
        $sector = Sector::query()->create(['name' => 'Emergência']);
        $requester = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $substitute = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $shift = Shift::query()->create([
            'date' => now()->addDay()->toDateString(),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        return [$requester, $substitute, $shift];
    }

    private function makeSwap(
        Shift $shift,
        User $requester,
        User $substitute,
        ShiftSwapStatus $status = ShiftSwapStatus::PendingProfessional,
    ): ShiftSwap {
        return ShiftSwap::query()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => $status,
        ]);
    }
}
