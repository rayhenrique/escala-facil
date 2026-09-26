<?php

namespace Tests\Feature\Models;

use App\Enums\AbsenceStatus;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EloquentModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_cast_domain_values_and_expose_their_relationships(): void
    {
        $category = Category::create(['name' => 'Médico Clínico']);
        $sector = Sector::create(['name' => 'Emergência']);
        $manager = $this->createUser('manager@example.test', UserRole::Manager);
        $professional = $this->createUser(
            'professional@example.test',
            UserRole::Professional,
            $category,
            $sector,
        );
        $substitute = $this->createUser(
            'substitute@example.test',
            UserRole::Professional,
            $category,
            $sector,
        );
        $shift = Shift::create([
            'date' => '2026-09-25',
            'start_time' => '07:00:00',
            'end_time' => '19:00:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);
        $swap = ShiftSwap::create([
            'shift_id' => $shift->id,
            'requester_id' => $professional->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::Approved,
            'manager_id' => $manager->id,
        ]);
        $absence = Absence::create([
            'shift_id' => $shift->id,
            'user_id' => $professional->id,
            'reason' => 'Atestado médico.',
            'attachment_path' => null,
            'status' => AbsenceStatus::Pending,
        ]);

        $this->assertSame(UserRole::Professional, $professional->role);
        $this->assertSame(ShiftStatus::Scheduled, $shift->status);
        $this->assertInstanceOf(CarbonImmutable::class, $shift->date);
        $this->assertSame(ShiftSwapStatus::Approved, $swap->status);
        $this->assertSame(AbsenceStatus::Pending, $absence->status);

        $this->assertTrue($professional->category->is($category));
        $this->assertTrue($professional->sector->is($sector));
        $this->assertTrue($professional->shifts->contains($shift));
        $this->assertTrue($professional->requestedSwaps->contains($swap));
        $this->assertTrue($substitute->substituteSwaps->contains($swap));
        $this->assertTrue($manager->managedSwaps->contains($swap));
        $this->assertTrue($professional->absences->contains($absence));

        $this->assertTrue($category->users->contains($professional));
        $this->assertTrue($category->shifts->contains($shift));
        $this->assertTrue($sector->users->contains($professional));
        $this->assertTrue($sector->shifts->contains($shift));

        $this->assertTrue($shift->category->is($category));
        $this->assertTrue($shift->sector->is($sector));
        $this->assertTrue($shift->user->is($professional));
        $this->assertTrue($shift->swaps->contains($swap));
        $this->assertTrue($shift->absences->contains($absence));

        $this->assertTrue($swap->shift->is($shift));
        $this->assertTrue($swap->requester->is($professional));
        $this->assertTrue($swap->substitute->is($substitute));
        $this->assertTrue($swap->manager->is($manager));

        $this->assertTrue($absence->shift->is($shift));
        $this->assertTrue($absence->user->is($professional));
    }

    public function test_user_reaches_the_limit_after_four_approved_swaps_in_the_month(): void
    {
        $category = Category::create(['name' => 'Enfermeiro Padrão']);
        $sector = Sector::create(['name' => 'UTI']);
        $professional = $this->createUser(
            'professional@example.test',
            UserRole::Professional,
            $category,
            $sector,
        );
        $colleague = $this->createUser(
            'colleague@example.test',
            UserRole::Professional,
            $category,
            $sector,
        );
        $shift = Shift::create([
            'date' => '2026-09-25',
            'start_time' => '07:00:00',
            'end_time' => '19:00:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $professional->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        $this->createSwap($shift, $professional, $colleague, ShiftSwapStatus::Approved, '2026-09-02');
        $this->createSwap($shift, $colleague, $professional, ShiftSwapStatus::Approved, '2026-09-08');
        $this->createSwap($shift, $professional, $colleague, ShiftSwapStatus::Approved, '2026-09-14');
        $this->createSwap($shift, $professional, $colleague, ShiftSwapStatus::Rejected, '2026-09-16');
        $this->createSwap($shift, $professional, $colleague, ShiftSwapStatus::Approved, '2026-08-28');

        $this->assertFalse($professional->hasReachedSwapLimit(9, 2026));

        $this->createSwap($shift, $colleague, $professional, ShiftSwapStatus::Approved, '2026-09-20');

        $this->assertTrue($professional->hasReachedSwapLimit(9, 2026));
        $this->assertFalse($professional->hasReachedSwapLimit(8, 2026));
    }

    private function createUser(
        string $email,
        UserRole $role,
        ?Category $category = null,
        ?Sector $sector = null,
    ): User {
        return User::create([
            'name' => fake()->name(),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'category_id' => $category?->id,
            'sector_id' => $sector?->id,
        ]);
    }

    private function createSwap(
        Shift $shift,
        User $requester,
        User $substitute,
        ShiftSwapStatus $status,
        string $createdAt,
    ): ShiftSwap {
        $swap = new ShiftSwap([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => $status,
            'manager_id' => null,
        ]);
        $swap->created_at = CarbonImmutable::parse($createdAt);
        $swap->updated_at = CarbonImmutable::parse($createdAt);
        $swap->save();

        return $swap;
    }
}
