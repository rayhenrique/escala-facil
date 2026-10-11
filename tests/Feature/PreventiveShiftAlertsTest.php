<?php

namespace Tests\Feature;

use App\Actions\Shifts\CreateShiftAction;
use App\Actions\Shifts\UpdateShiftAction;
use App\Enums\UserRole;
use App\Http\Requests\SaveShiftRequest;
use App\Livewire\Manager\ShiftManager;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use App\Support\ShiftAssignmentAnalysis;
use App\Support\ShiftReallocation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class PreventiveShiftAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        $this->travelTo(now()->setDate(2026, 10, 30)->startOfDay());
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $professional = User::factory()->create(['sector_id' => Sector::factory()->create()->id, 'category_id' => Category::factory()->create()->id]);
        $this->actingAs($manager);

        return [$manager, $professional];
    }

    private function payload(User $professional, array $changes = []): array
    {
        return $changes + ['date' => '2026-10-31', 'start_time' => '19:00', 'end_time' => '07:00', 'end_date' => '2026-11-01', 'sector_id' => $professional->sector_id, 'category_id' => $professional->category_id, 'user_id' => $professional->id, 'status' => 'scheduled'];
    }

    private function preview(array $payload, ?int $id = null): array
    {
        $candidate = new Shift((new SaveShiftRequest)->validatePayload($payload));
        $candidate->id = $id;

        return app(ShiftAssignmentAnalysis::class)->preview($candidate);
    }

    public function test_month_and_midnight_overlap_is_information_for_editing_and_block_for_reallocation(): void
    {
        [, $user] = $this->team();
        $other = Shift::factory()->create($this->payload($user, ['date' => '2026-11-01', 'start_time' => '06:59', 'end_time' => '08:00']));
        $payload = $this->payload($user);
        $preview = $this->preview($payload);
        $this->assertSame([], $preview['blocks']);
        $this->assertCount(1, $preview['conflicts']);
        $this->assertStringContainsString('#'.$other->id, $preview['warnings'][0]);
        $this->assertStringContainsString('01/11/2026', $preview['warnings'][0]);
        $this->assertStringContainsString('Ajuste os horários', $preview['warnings'][0]);
        $analysis = app(ShiftAssignmentAnalysis::class);
        $candidate = new Shift($payload);
        $this->assertCount(1, $analysis->analyze($candidate, $user, $analysis->schedule($candidate, [$user->id]), overlapBlocks: true)['blocks']);
    }

    public function test_consecutive_shifts_are_warnings_in_both_directions_and_one_minute_gap_is_not(): void
    {
        [, $user] = $this->team();
        $other = Shift::factory()->create($this->payload($user, ['date' => '2026-11-01', 'start_time' => '07:00', 'end_time' => '19:00']));
        $preview = $this->preview($this->payload($user));
        $this->assertSame([], $preview['conflicts']);
        $this->assertCount(1, $preview['doubles']);
        $earlier = new Shift($this->payload($user));
        $this->assertCount(1, app(ShiftAssignmentAnalysis::class)->availability($other, $user, collect([$earlier]))['doubles']);
        $other->update(['start_time' => '07:01']);
        $this->assertSame([], $this->preview($this->payload($user))['warnings']);
    }

    public function test_24_hours_legacy_night_minimum_duration_and_non_scheduled_rows_use_real_intervals(): void
    {
        [, $user] = $this->team();
        $other = Shift::factory()->create($this->payload($user, ['date' => '2026-10-30', 'end_date' => null, 'start_time' => '19:00', 'end_time' => '07:01']));
        $payload = $this->payload($user, ['start_time' => '07:00', 'end_time' => '07:00']);
        $this->assertStringContainsString('24h', $this->preview($payload)['period']);
        $this->assertCount(1, $this->preview($payload)['conflicts']);
        $other->update(['status' => 'absence_reported']);
        $this->assertSame([], $this->preview($payload)['warnings']);
        $minimum = $this->preview($this->payload($user, ['start_time' => '00:00', 'end_time' => '00:01', 'end_date' => '2026-10-31']));
        $this->assertStringContainsString('1 min', $minimum['period']);
        $this->assertSame([], $minimum['warnings']);
    }

    public function test_edit_excludes_itself_but_still_detects_other_shift(): void
    {
        [, $user] = $this->team();
        $payload = $this->payload($user);
        $shift = Shift::factory()->create($payload);
        $this->assertSame([], $this->preview($payload, $shift->id)['warnings']);
        $other = Shift::factory()->create($payload);
        $preview = $this->preview($payload, $shift->id);
        $this->assertCount(1, $preview['conflicts']);
        $this->assertStringContainsString('#'.$other->id.':', $preview['conflicts'][0]);
    }

    public function test_creation_requires_warning_review_and_keeps_previous_overlap_policy(): void
    {
        [$manager, $user] = $this->team();
        $payload = $this->payload($user);
        Shift::factory()->create($payload);
        $count = OperationalAuditEvent::count();
        try {
            app(CreateShiftAction::class)->execute($manager, $payload);
            $this->fail('Review must be required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shift', $exception->errors());
        }
        $this->assertDatabaseCount('shifts', 1);
        $this->assertSame($count, OperationalAuditEvent::count());
        $created = app(CreateShiftAction::class)->execute($manager, $payload, $this->preview($payload)['fingerprint']);
        $this->assertSame($user->id, $created->user_id);
        $this->assertDatabaseCount('shifts', 2);
    }

    public function test_backend_rechecks_schedule_and_cadastre_after_review_and_keeps_failed_changes_out_of_audit(): void
    {
        [$manager, $user] = $this->team();
        $payload = $this->payload($user);
        $preview = $this->preview($payload);
        Shift::factory()->create($payload);
        $count = OperationalAuditEvent::count();
        try {
            app(CreateShiftAction::class)->execute($manager, $payload, $preview['fingerprint']);
            $this->fail('Stale availability must be refused.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('shifts', 1);
        $this->assertSame($count, OperationalAuditEvent::count());
        $preview = $this->preview($payload);
        $user->update(['category_id' => null]);
        try {
            app(CreateShiftAction::class)->execute($manager, $payload, $preview['fingerprint']);
            $this->fail('Incompatible professional must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('user_id', $exception->errors());
        }
        $this->assertDatabaseCount('shifts', 1);
    }

    public function test_edit_revalidates_existing_signature_and_warning_acknowledgment(): void
    {
        [$manager, $user] = $this->team();
        $payload = $this->payload($user);
        $shift = Shift::factory()->create($payload);
        $signature = app(ShiftReallocation::class)->shiftSignature($shift->fresh());
        $preview = $this->preview($payload, $shift->id);
        Shift::factory()->create($payload);
        try {
            app(UpdateShiftAction::class)->execute($manager, $shift->id, $payload, $signature, $preview['fingerprint']);
            $this->fail('Changed schedule must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('shift', $exception->errors());
        }
        $preview = $this->preview($payload, $shift->id);
        app(UpdateShiftAction::class)->execute($manager, $shift->id, $payload, $signature, $preview['fingerprint']);
        $shift->update(['end_time' => '06:00']);
        try {
            app(UpdateShiftAction::class)->execute($manager, $shift->id, $payload, $signature, $preview['fingerprint']);
            $this->fail('Stale edit must be refused.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('outra operação', $exception->errors()['shift'][0]);
        }
    }

    public function test_livewire_shows_review_preserves_values_and_saves_only_after_confirmation(): void
    {
        [$manager, $user] = $this->team();
        $other = Shift::factory()->create($this->payload($user));
        $component = Livewire::actingAs($manager)->test(ShiftManager::class)->call('openCreateModal')
            ->set('date', '2026-10-31')->set('startTime', '19:00')->set('endTime', '07:00')->set('endDate', '2026-11-01')
            ->set('sectorId', (string) $user->sector_id)->set('categoryId', (string) $user->category_id)->set('userId', (string) $user->id)
            ->call('save')->assertHasNoErrors()->assertSet('showFormModal', true)->assertSee('Avisos para revisão')->assertSee('Conflito com o plantão #'.$other->id)
            ->call('returnToShiftForm')->assertSet('startTime', '19:00')->assertSet('shiftPreview', null)->call('save');
        $this->assertDatabaseCount('shifts', 1);
        $component->call('save')->assertHasNoErrors()->assertSet('showFormModal', false);
        $this->assertDatabaseCount('shifts', 2);
    }

    public function test_coverage_warning_and_professional_authorization_do_not_add_workload_rules(): void
    {
        [$manager, $user] = $this->team();
        $payload = $this->payload($user, ['user_id' => null, 'status' => 'uncovered']);
        $preview = $this->preview($payload);
        $this->assertCount(1, $preview['warnings']);
        $this->assertStringContainsString('cobertura', $preview['warnings'][0]);
        app(CreateShiftAction::class)->execute($manager, $payload, $preview['fingerprint']);
        $this->expectException(AuthorizationException::class);
        app(CreateShiftAction::class)->execute($user, $payload, $preview['fingerprint']);
    }

    public function test_role_sector_and_category_incompatibilities_are_mandatory_blocks(): void
    {
        [$manager, $user] = $this->team();
        $analysis = app(ShiftAssignmentAnalysis::class);
        $candidate = new Shift($this->payload($user));
        $this->assertSame([], $analysis->compatibility($user, $candidate));
        $this->assertNotEmpty($analysis->analyze($candidate, $manager, collect())['blocks']);
        foreach (['sector_id', 'category_id'] as $field) {
            $invalid = clone $candidate;
            $invalid->$field = null;
            $result = $analysis->analyze($invalid, $user, collect());
            $this->assertCount(1, $result['blocks']);
            $this->assertStringContainsString('cadastro compatível', $result['blocks'][0]);
        }
    }
}
