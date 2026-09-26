<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PhaseSixSwapRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_approved_participations_block_a_fifth_swap_request(): void
    {
        [, $professional, $substitute, $category, $sector] = $this->team();
        $this->createApprovedParticipations($professional, $substitute, $category, $sector);
        $candidateShift = $this->shift($professional, $category, $sector, 10);

        try {
            app(CreateShiftSwapAction::class)->execute($professional, [
                'shift_id' => $candidateShift->id,
                'substitute_id' => $substitute->id,
            ]);
            $this->fail('A quinta solicitação deveria ter sido bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shift_id', $exception->errors());
            $this->assertSame(
                'Você atingiu o limite mensal de quatro trocas aprovadas.',
                $exception->errors()['shift_id'][0],
            );
        }

        $this->assertDatabaseCount('shift_swaps', User::MONTHLY_SWAP_LIMIT);
        $this->assertDatabaseMissing('shift_swaps', [
            'shift_id' => $candidateShift->id,
            'status' => ShiftSwapStatus::PendingProfessional->value,
        ]);
    }

    public function test_four_approved_participations_disable_swap_actions_in_the_schedule(): void
    {
        [, $professional, $substitute, $category, $sector] = $this->team();
        $this->createApprovedParticipations($professional, $substitute, $category, $sector);
        $this->shift($professional, $category, $sector, 10);

        Livewire::actingAs($professional)
            ->test(MySchedule::class)
            ->assertSee('Você atingiu a cota de quatro trocas aprovadas neste mês.')
            ->assertSeeHtml('disabled');
    }

    public function test_substitute_at_the_limit_cannot_accept_a_fifth_swap(): void
    {
        [, $requester, $substitute, $category, $sector] = $this->team();
        $this->createApprovedParticipations($substitute, $requester, $category, $sector);
        $candidateShift = $this->shift($requester, $category, $sector, 10);
        $candidateSwap = $this->makeSwap($candidateShift, $requester, $substitute);

        $this->assertThrows(
            fn () => app(AcceptShiftSwapAction::class)->execute($substitute, $candidateSwap->id),
            AuthorizationException::class,
        );

        $this->assertSame(ShiftSwapStatus::PendingProfessional, $candidateSwap->refresh()->status);
        $this->assertNull($candidateSwap->manager_id);
    }

    public function test_manager_cannot_approve_when_a_participant_reaches_the_limit_after_acceptance(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();
        $candidateShift = $this->shift($requester, $category, $sector, 10);
        $candidateSwap = $this->makeSwap(
            $candidateShift,
            $requester,
            $substitute,
            ShiftSwapStatus::PendingManager,
        );
        $this->createApprovedParticipations($substitute, $requester, $category, $sector);

        $this->assertThrows(
            fn () => app(DecideShiftSwapAction::class)->approve($manager, $candidateSwap->id),
            AuthorizationException::class,
        );

        $this->assertSame(ShiftSwapStatus::PendingManager, $candidateSwap->refresh()->status);
        $this->assertNull($candidateSwap->manager_id);
        $this->assertSame($requester->id, $candidateShift->refresh()->user_id);
    }

    public function test_forged_swap_request_is_blocked_for_a_professional_from_another_sector(): void
    {
        [, $requester, , $category, $sector] = $this->team();
        $otherSector = Sector::query()->create(['name' => 'UTI']);
        $incompatible = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $otherSector->id,
        ]);

        $this->assertIncompatibleRequestIsRejected($requester, $incompatible, $category, $sector);
    }

    public function test_forged_swap_request_is_blocked_for_a_professional_from_another_category(): void
    {
        [, $requester, , $category, $sector] = $this->team();
        $otherCategory = Category::query()->create(['name' => 'Enfermeiro Padrão']);
        $incompatible = User::factory()->create([
            'category_id' => $otherCategory->id,
            'sector_id' => $sector->id,
        ]);

        $this->assertIncompatibleRequestIsRejected($requester, $incompatible, $category, $sector);
    }

    public function test_stale_incompatible_assignment_is_rechecked_when_substitute_accepts(): void
    {
        [, $requester, $substitute, $category, $sector] = $this->team();
        $candidateShift = $this->shift($requester, $category, $sector, 10);
        $candidateSwap = $this->makeSwap($candidateShift, $requester, $substitute);
        $otherCategory = Category::query()->create(['name' => 'Enfermeiro Padrão']);
        $substitute->update(['category_id' => $otherCategory->id]);

        $this->assertThrows(
            fn () => app(AcceptShiftSwapAction::class)->execute($substitute->refresh(), $candidateSwap->id),
            AuthorizationException::class,
        );

        $this->assertSame(ShiftSwapStatus::PendingProfessional, $candidateSwap->refresh()->status);
    }

    /** @return array{User, User, User, Category, Sector} */
    private function team(): array
    {
        $category = Category::query()->create(['name' => 'Médico Clínico']);
        $sector = Sector::query()->create(['name' => 'Emergência']);
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

    private function createApprovedParticipations(
        User $professional,
        User $counterpart,
        Category $category,
        Sector $sector,
    ): void {
        foreach (range(1, User::MONTHLY_SWAP_LIMIT) as $index) {
            $professionalIsRequester = $index % 2 === 1;
            $shift = $this->shift(
                $professionalIsRequester ? $counterpart : $professional,
                $category,
                $sector,
                $index,
            );

            $this->makeSwap(
                $shift,
                $professionalIsRequester ? $professional : $counterpart,
                $professionalIsRequester ? $counterpart : $professional,
                ShiftSwapStatus::Approved,
            );
        }

        $this->assertTrue($professional->hasReachedSwapLimit(now()->month, now()->year));
    }

    private function assertIncompatibleRequestIsRejected(
        User $requester,
        User $incompatible,
        Category $category,
        Sector $sector,
    ): void {
        $shift = $this->shift($requester, $category, $sector, 10);

        try {
            app(CreateShiftSwapAction::class)->execute($requester, [
                'shift_id' => $shift->id,
                'substitute_id' => $incompatible->id,
            ]);
            $this->fail('Uma troca incompatível deveria ter sido bloqueada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('substitute_id', $exception->errors());
            $this->assertSame(
                'O substituto deve ser outro profissional da mesma categoria e do mesmo setor.',
                $exception->errors()['substitute_id'][0],
            );
        }

        $this->assertDatabaseCount('shift_swaps', 0);
    }

    private function shift(
        User $professional,
        Category $category,
        Sector $sector,
        int $daysAhead,
    ): Shift {
        return Shift::query()->create([
            'date' => today()->addDays($daysAhead),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);
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
