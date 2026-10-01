<?php

namespace Tests\Feature;

use App\Actions\Absences\ReportAbsenceAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OperationalRobustnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfMonth()->addDays(5));
    }

    public function test_factories_create_a_valid_domain_graph(): void
    {
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
        $professional = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        $shift = Shift::factory()->create([
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $substitute = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        $swap = ShiftSwap::factory()->create([
            'shift_id' => $shift->id,
            'requester_id' => $professional->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::PendingProfessional,
        ]);

        $absence = Absence::factory()->create([
            'shift_id' => $shift->id,
            'user_id' => $professional->id,
        ]);

        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseCount('sectors', 1);
        $this->assertDatabaseCount('shifts', 1);
        $this->assertDatabaseCount('shift_swaps', 1);
        $this->assertDatabaseCount('absences', 1);
        $this->assertTrue($swap->wasRecentlyCreated);
        $this->assertTrue($absence->wasRecentlyCreated);
    }

    public function test_second_approval_is_blocked_when_quota_fills_between_acceptance_and_decision(): void
    {
        [$manager, $requester, $substitute, $category, $sector] = $this->team();

        // Três participações aprovadas: a cota ainda permite mais uma.
        foreach (range(1, User::MONTHLY_SWAP_LIMIT - 1) as $index) {
            $this->approvedSwap($requester, $substitute, $category, $sector, $index);
        }

        // Duas trocas aceitas pelo substituto aguardam a gerência.
        $firstShift = $this->shift($requester, $category, $sector, 10);
        $firstSwap = $this->pendingManagerSwap($firstShift, $requester, $substitute);

        $secondShift = $this->shift($requester, $category, $sector, 11);
        $secondSwap = $this->pendingManagerSwap($secondShift, $requester, $substitute);

        app(DecideShiftSwapAction::class)->approve($manager, $firstSwap->id);

        $this->assertSame(ShiftSwapStatus::Approved, $firstSwap->refresh()->status);
        $this->assertSame($substitute->id, $firstShift->refresh()->user_id);
        $this->assertTrue($requester->refresh()->hasReachedSwapLimit(now()->month, now()->year));

        try {
            app(DecideShiftSwapAction::class)->approve($manager, $secondSwap->id);
            $this->fail('A segunda aprovação deveria ter sido bloqueada pela cota.');
        } catch (AuthorizationException) {
            // Esperado: a cota foi preenchida pela primeira aprovação.
        }

        $this->assertSame(ShiftSwapStatus::PendingManager, $secondSwap->refresh()->status);
        $this->assertNull($secondSwap->manager_id);
        $this->assertSame($requester->id, $secondShift->refresh()->user_id);
    }

    public function test_absence_attachment_is_private_and_visible_only_to_owner_and_managers(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$manager, $professional, , $category, $sector] = $this->team();
        $outsider = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $shift = $this->shift($professional, $category, $sector, 10);

        $absence = app(ReportAbsenceAction::class)->execute($professional, [
            'shift_id' => $shift->id,
            'reason' => 'Atendimento médico de urgência comprovado.',
            'attachment' => UploadedFile::fake()->create('atestado.pdf', 120, 'application/pdf'),
        ]);

        $this->assertStringStartsWith('absence-attachments/', $absence->attachment_path);
        Storage::disk('local')->assertExists($absence->attachment_path);
        Storage::disk('public')->assertMissing($absence->attachment_path);

        $this->assertTrue($professional->can('view', $absence));
        $this->assertTrue($manager->can('view', $absence));
        $this->assertFalse($outsider->can('view', $absence));
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

    private function approvedSwap(User $requester, User $substitute, Category $category, Sector $sector, int $daysAhead): ShiftSwap
    {
        $shift = $this->shift($requester, $category, $sector, $daysAhead);

        return ShiftSwap::factory()->approved()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
        ]);
    }

    private function pendingManagerSwap(Shift $shift, User $requester, User $substitute): ShiftSwap
    {
        return ShiftSwap::factory()->pendingManager()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
        ]);
    }
}
