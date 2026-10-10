<?php

namespace Tests\Feature;

use App\Actions\Shifts\ReallocateShiftAction;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\ShiftManager;
use App\Livewire\MonthlyCalendar;
use App\Models\Absence;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftReallocation as Audit;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftReallocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftReallocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_is_read_only_and_confirmation_updates_queue_and_records_audit(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $absence = Absence::factory()->create(['shift_id' => $shift->id, 'user_id' => $professional->id]);
        $swap = ShiftSwap::factory()->create(['shift_id' => $shift->id, 'status' => 'rejected']);
        $absenceBefore = $absence->getAttributes();
        $swapBefore = $swap->fresh()->getAttributes();
        $professional->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'historical', 'data' => ['message' => 'Histórico preservado']]);
        $notificationBefore = $professional->notifications()->sole()->getAttributes();
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->set('period', '7')
            ->call('openReallocation', $shift->id)->assertSee($professional->name)
            ->set('reallocationUserId', (string) $professional->id)->call('reviewReallocation')
            ->assertSee('Prévia da alteração')->assertSee('Confirmar realocação');
        $this->assertSame(ShiftStatus::Uncovered, $shift->fresh()->status);
        $this->assertDatabaseCount('shift_reallocations', 0);
        $panel->call('confirmReallocation')->assertHasNoErrors()->assertSet('reallocationShiftId', null)
            ->assertSet('period', '7')->assertDispatched('shift-reallocated', shiftId: $shift->id)
            ->assertDontSeeHtml('data-attention="uncovered-'.$shift->id.'"');
        $this->assertSame(ShiftStatus::Scheduled, $shift->refresh()->status);
        $this->assertSame($professional->id, $shift->user_id);
        $audit = Audit::query()->sole();
        $this->assertSame($manager->id, $audit->manager_id);
        $this->assertSame($manager->name, $audit->manager_name);
        $this->assertNotNull($audit->performed_at);
        $this->assertNull($audit->before['user_id']);
        $this->assertSame($professional->id, $audit->after['user_id']);
        $this->assertSame($audit->before['date'], $audit->after['date']);
        $this->assertEquals($absenceBefore, $absence->fresh()->getAttributes());
        $this->assertEquals($swapBefore, $swap->fresh()->getAttributes());
        $this->assertSame($notificationBefore, $professional->notifications()->sole()->getAttributes());
        Livewire::actingAs($manager)->test(ShiftManager::class)->set('focus', $shift->id)
            ->assertSee('Histórico de realocação')->assertSee($professional->name)->assertSee($manager->name);
    }

    public function test_candidates_exclude_wrong_roles_sectors_categories_and_real_conflicts(): void
    {
        [$manager, $shift, $professional] = $this->team();
        User::factory()->create(['sector_id' => $shift->sector_id, 'category_id' => $shift->category_id, 'role' => UserRole::Manager]);
        User::factory()->create(['sector_id' => $shift->sector_id, 'category_id' => Category::factory()->create()->id]);
        User::factory()->create(['category_id' => $shift->category_id, 'sector_id' => Sector::factory()->create()->id]);
        $busy = User::factory()->create(['sector_id' => $shift->sector_id, 'category_id' => $shift->category_id]);
        $this->assigned($shift, $busy, '08:00', '09:00');
        $result = app(ShiftReallocation::class)->candidates($shift);
        $this->assertSame([$professional->id], array_column($result['professionals'], 'id'));
        $this->assertSame(1, $result['unavailable']);
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openReallocation', $shift->id)
            ->set('reallocationUserId', (string) $busy->id)->call('reviewReallocation')->assertHasErrors(['reallocationUserId']);
    }

    public function test_night_shift_conflict_and_month_boundary_are_real_intervals(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 30)->startOfDay());
        [$manager, $shift, $professional] = $this->team();
        $shift->update(['date' => '2026-10-31', 'start_time' => '19:00', 'end_time' => '07:00', 'end_date' => '2026-11-01']);
        $overlap = $this->assigned($shift, $professional, '06:59', '08:00', '2026-11-01');
        $this->assertSame([], app(ShiftReallocation::class)->candidates($shift->fresh())['professionals']);
        $overlap->update(['start_time' => '07:00']);
        $result = app(ShiftReallocation::class)->candidates($shift->fresh());
        $this->assertCount(1, $result['professionals']);
        $this->assertStringContainsString('Dobra', $result['professionals'][0]['doubles'][0]);
        $this->approve($manager, $shift->fresh(), $professional);
        $this->assertSame('2026-11-01', $shift->refresh()->end_date->toDateString());
    }

    public function test_24_hour_shift_blocks_previous_night_overlap_and_allows_adjacent_shift(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $shift->update(['end_time' => '07:00', 'end_date' => $shift->date->addDay()]);
        $night = $this->assigned($shift, $professional, '19:00', '07:01', $shift->date->subDay()->toDateString(), $shift->date->toDateString());
        $this->assertSame([], app(ShiftReallocation::class)->candidates($shift->fresh())['professionals']);
        $night->update(['end_time' => '07:00']);
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('openReallocation', $shift->id)
            ->assertSee('24h')->assertSee('Dobra')
            ->set('reallocationUserId', (string) $professional->id)->call('reviewReallocation')->assertSee('dia seguinte')
            ->call('confirmReallocation')->assertHasNoErrors();
        $this->assertSame(1440, $shift->refresh()->period()->durationMinutes);
        $this->assertSame($professional->id, $shift->user_id);
    }

    public function test_schedule_change_after_preview_blocks_confirmation(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $panel = $this->preview($manager, $shift, $professional);
        $this->assigned($shift, $professional, '08:00', '10:00');
        $panel->call('confirmReallocation')->assertHasErrors(['reallocationUserId']);
        $this->assertSame(ShiftStatus::Uncovered, $shift->refresh()->status);
        $this->assertDatabaseCount('shift_reallocations', 0);
    }

    public function test_new_double_requires_another_preview_instead_of_silent_confirmation(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $panel = $this->preview($manager, $shift, $professional);
        $this->assigned($shift, $professional, '19:00', '20:00');
        $panel->call('confirmReallocation')->assertHasErrors(['reallocation']);
        $this->assertDatabaseCount('shift_reallocations', 0);
    }

    public function test_changed_target_and_incompatible_registration_are_revalidated(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $panel = $this->preview($manager, $shift, $professional);
        $professional->update(['sector_id' => Sector::factory()->create()->id]);
        $panel->call('confirmReallocation')->assertHasErrors(['reallocationUserId']);
        $shift->update(['start_time' => '08:00']);
        $panel->call('returnToReallocation')->call('reviewReallocation')->assertHasErrors(['reallocation']);
        $this->assertDatabaseCount('shift_reallocations', 0);
    }

    public function test_two_previews_cannot_reassign_the_same_plantao(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $other = User::factory()->create(['sector_id' => $shift->sector_id, 'category_id' => $shift->category_id]);
        $one = $this->preview($manager, $shift, $professional);
        $two = $this->preview($manager, $shift, $other);
        $one->call('confirmReallocation')->assertHasNoErrors();
        $two->call('confirmReallocation')->assertHasErrors(['reallocation']);
        $this->assertSame($professional->id, $shift->refresh()->user_id);
        $this->assertDatabaseCount('shift_reallocations', 1);
    }

    public function test_same_professional_cannot_fill_two_overlapping_targets(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $otherShift = $shift->replicate();
        $otherShift->save();
        $one = $this->preview($manager, $shift, $professional);
        $two = $this->preview($manager, $otherShift, $professional);
        $one->call('confirmReallocation')->assertHasNoErrors();
        $two->call('confirmReallocation')->assertHasErrors(['reallocationUserId']);
        $this->assertSame(ShiftStatus::Uncovered, $otherShift->refresh()->status);
        $this->assertDatabaseCount('shift_reallocations', 1);
    }

    public function test_professional_and_revoked_manager_have_no_permission(): void
    {
        [$manager, $shift, $professional] = $this->team();
        Livewire::actingAs($professional)->test(MonthlyCalendar::class)->call('openReallocation', $shift->id)->assertForbidden();
        $panel = $this->preview($manager, $shift, $professional);
        $manager->update(['role' => UserRole::Professional]);
        $panel->call('confirmReallocation')->assertForbidden();
        $this->assertDatabaseCount('shift_reallocations', 0);
    }

    public function test_confirmation_uses_locked_reviewed_professional_and_requires_preview(): void
    {
        [$manager, $shift, $professional] = $this->team();
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openReallocation', $shift->id)
            ->call('confirmReallocation')->assertNotFound();
        $panel = $this->preview($manager, $shift, $professional);
        $other = User::factory()->create(['sector_id' => $shift->sector_id, 'category_id' => $shift->category_id]);
        $panel->set('reallocationUserId', (string) $other->id)->call('confirmReallocation')->assertHasNoErrors();
        $this->assertSame($professional->id, $shift->refresh()->user_id);
    }

    public function test_calendar_updates_and_active_overnight_shift_can_be_filled_without_changing_dates(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $this->travelTo(today()->setTime(2, 0));
        $shift->update(['date' => today()->subDay(), 'start_time' => '19:00', 'end_time' => '07:00', 'end_date' => today()]);
        Livewire::actingAs($manager)->test(MonthlyCalendar::class)->set('month', $shift->date->format('Y-m'))->set('selectedDate', $shift->date->toDateString())
            ->call('openReallocation', $shift->id)->set('reallocationUserId', (string) $professional->id)
            ->call('reviewReallocation')->call('confirmReallocation')->assertHasNoErrors()->assertSee($professional->name);
        $this->assertSame(today()->subDay()->toDateString(), $shift->refresh()->date->toDateString());
    }

    public function test_completed_or_already_scheduled_shifts_are_not_reallocated(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $shift->update(['date' => today()->subDay(), 'end_date' => today()->subDay()]);
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openReallocation', $shift->id)->assertForbidden();
        $this->assertDatabaseCount('shift_reallocations', 0);
    }

    public function test_audit_failure_rolls_back_assignment_and_audit_survives_record_deletion(): void
    {
        [$manager, $shift, $professional] = $this->team();
        Audit::creating(fn () => throw new \RuntimeException('Audit unavailable'));
        try {
            $this->approve($manager, $shift, $professional);
            $this->fail('Audit failures must rollback.');
        } catch (\RuntimeException) {
            $this->assertSame(ShiftStatus::Uncovered, $shift->fresh()->status);
            $this->assertDatabaseCount('shift_reallocations', 0);
        } finally {
            Audit::flushEventListeners();
        }
        $audit = $this->approve($manager, $shift, $professional);
        $shift->delete();
        $this->assertNull($audit->refresh()->shift_id);
        $this->assertSame($shift->id, $audit->before['id']);
        $this->assertSame($professional->name, $audit->after['professional']);
    }

    public function test_candidate_loading_has_a_constant_query_count(): void
    {
        [$manager, $shift, $professional] = $this->team();
        User::factory()->count(20)->create(['sector_id' => $shift->sector_id, 'category_id' => $shift->category_id]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $result = app(ShiftReallocation::class)->candidates($shift);
        $this->assertCount(21, $result['professionals']);
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_old_edit_form_cannot_overwrite_a_confirmed_reallocation(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $editor = Livewire::actingAs($manager)->test(ShiftManager::class)->call('edit', $shift->id);
        $this->approve($manager, $shift->fresh(), $professional);
        $editor->call('save')->assertHasErrors(['shift']);
        $this->assertSame($professional->id, $shift->refresh()->user_id);
        $this->assertSame(ShiftStatus::Scheduled, $shift->status);
        $this->assertDatabaseCount('shift_reallocations', 1);
    }

    public function test_role_change_of_candidate_blocks_confirmation(): void
    {
        [$manager, $shift, $professional] = $this->team();
        $panel = $this->preview($manager, $shift, $professional);
        $professional->update(['role' => UserRole::Manager]);
        $panel->call('confirmReallocation')->assertHasErrors(['reallocationUserId']);
        $this->assertSame(ShiftStatus::Uncovered, $shift->refresh()->status);
        $this->assertDatabaseCount('shift_reallocations', 0);
    }

    private function preview(User $manager, Shift $shift, User $professional)
    {
        return Livewire::actingAs($manager)->test(Dashboard::class)->call('openReallocation', $shift->id)
            ->set('reallocationUserId', (string) $professional->id)->call('reviewReallocation')->assertHasNoErrors();
    }

    private function approve(User $manager, Shift $shift, User $professional): Audit
    {
        $snapshot = app(ShiftReallocation::class)->preview($shift, $professional)['fingerprint'];

        return app(ReallocateShiftAction::class)->execute($manager, $shift->id, $professional->id, $snapshot);
    }

    private function assigned(Shift $shift, User $user, string $start, string $end, ?string $date = null, ?string $endDate = null): Shift
    {
        return Shift::factory()->create(['sector_id' => $shift->sector_id, 'category_id' => $shift->category_id,
            'user_id' => $user->id, 'date' => $date ?? $shift->date->toDateString(), 'start_time' => $start,
            'end_time' => $end, 'end_date' => $endDate ?? $date ?? $shift->date->toDateString(), 'status' => ShiftStatus::Scheduled]);
    }

    private function team(): array
    {
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $professional = User::factory()->create(['category_id' => $category->id, 'sector_id' => $sector->id]);
        $shift = Shift::factory()->create(['category_id' => $category->id, 'sector_id' => $sector->id,
            'date' => today()->addDay(), 'end_date' => today()->addDay(), 'start_time' => '07:00', 'end_time' => '19:00',
            'user_id' => null, 'status' => ShiftStatus::Uncovered]);

        return [$manager, $shift, $professional];
    }
}
