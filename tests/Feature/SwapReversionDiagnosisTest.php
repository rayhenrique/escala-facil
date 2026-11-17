<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\OperationalAuditContext;
use App\Support\ShiftSwapReversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SwapReversionDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-11-01 12:00:00');
    }

    /** Synthetic IDs matching the reported case do not represent production data. */
    private function fixture(string $mode = 'modern'): array
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $profile = ['sector_id' => Sector::factory()->create()->id, 'category_id' => Category::factory()->create()->id];
        $requester = User::factory()->create($profile);
        $substitute = User::factory()->create($profile);
        $shift = Shift::factory()->create($profile + ['id' => 1675, 'user_id' => $requester->id, 'date' => '2026-11-20', 'start_time' => '07:00', 'end_time' => '19:00', 'end_date' => '2026-11-20']);
        $counter = Shift::factory()->create($profile + ['id' => 1468, 'user_id' => $substitute->id, 'date' => '2026-11-22', 'start_time' => '07:00', 'end_time' => '19:00', 'end_date' => '2026-11-22']);
        $attributes = ['id' => 190, 'shift_id' => $shift->id, 'substitute_shift_id' => $counter->id, 'requester_id' => $requester->id, 'substitute_id' => $substitute->id];
        if ($mode === 'missing_creation') {
            // Build a legacy fixture without creating/deleting immutable events.
            DB::table('shift_swaps')->insert($attributes + ['status' => 'pending_manager', 'created_at' => now(), 'updated_at' => now()]);
            $swap = ShiftSwap::findOrFail(190);
        } else {
            $swap = ShiftSwap::factory()->pendingManager()->create($attributes);
        }
        if ($mode === 'modern') {
            app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        } else {
            app(OperationalAuditContext::class)->transaction($manager, function () use ($mode, $manager, $requester, $substitute, $shift, $counter, $swap): void {
                $changes = ['status' => ['before' => 'pending_manager', 'after' => 'approved']];
                if ($mode === 'partial_state') {
                    $state = app(ShiftSwapReversion::class)->approvalState($swap);
                    unset($state['before'][$shift->id]['user_id']);
                    $changes['approval_state'] = ['before' => null, 'after' => $state];
                }
                DB::table('shift_swaps')->where('id', $swap->id)->update(['status' => 'approved', 'approved_at' => now(), 'manager_id' => $manager->id]);
                if ($mode !== 'missing_approval') {
                    OperationalAuditEvent::create([
                        'entity_type' => 'swap', 'entity_id' => $swap->id, 'action' => 'approved', 'author_id' => $manager->id,
                        'occurred_at' => now(), 'operation_id' => app(OperationalAuditContext::class)->operationId(), 'changes' => $changes,
                    ]);
                }
                $shift->update(['user_id' => $substitute->id]);
                $counter->update(['user_id' => $requester->id]);
            });
        }

        return [$swap->fresh(), $manager, $requester, $shift->fresh(), $counter->fresh()];
    }

    private function diagnosis(array $options = [], int $exitCode = 0): array
    {
        $this->assertSame($exitCode, Artisan::call('app:diagnose-swap-reversion', $options + ['id' => 190, '--json' => true]));

        return json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_reported_id_order_is_not_a_blocker_and_preview_matches_locked_collection(): void
    {
        [$swap, $manager] = $this->fixture();
        $review = app(ShiftSwapReversion::class)->inspect($swap);
        $this->assertTrue($review['can_revert'], implode(' ', $review['blocks']));
        $locked = Shift::query()->whereKey([1675, 1468])->orderBy('id')->get()->keyBy('id');
        $confirmed = app(ShiftSwapReversion::class)->inspect($swap->fresh(), $locked);
        $this->assertSame($review['fingerprint'], $confirmed['fingerprint']);
        $report = $this->diagnosis(['--manager' => $manager->id, '--expected-snapshot' => $review['fingerprint']]);
        $this->assertSame('embedded', $report['approval']['source']);
        $this->assertTrue($report['review']['expected_snapshot_matches']);
        $this->assertTrue($report['authorization']['reviewReversion']['allowed']);
        $this->assertSame([1675, 1468], array_column($report['shifts'], 'id'));
        $this->assertSame([], $report['shifts'][0]['strict_differences']);
    }

    public function test_complete_legacy_trail_is_accepted_without_approval_state_and_missing_creation_is_explained(): void
    {
        $this->fixture('legacy');
        $report = $this->diagnosis();
        $this->assertSame('reconstructed_legacy', $report['approval']['source']);
        $this->assertTrue($report['approval']['legacy_swap_trail']['starts_with_created']);
        $this->assertTrue($report['review']['can_revert']);
    }

    public function test_a_missing_creation_event_is_not_filled_using_current_assignments(): void
    {
        $this->fixture('missing_creation');
        $report = $this->diagnosis(exitCode: 1);
        $this->assertSame('legacy_unavailable', $report['approval']['source']);
        $this->assertFalse($report['approval']['legacy_swap_trail']['starts_with_created']);
        $this->assertFalse($report['review']['can_revert']);
        $this->assertStringContainsString('evidência completa', implode(' ', $report['review']['blocks']));
        $this->assertNull($report['shifts'][0]['expected_before']);
    }

    public function test_absent_and_ambiguous_approval_events_are_distinguished(): void
    {
        $this->fixture('missing_approval');
        $report = $this->diagnosis(exitCode: 1);
        $this->assertSame('missing_approval_event', $report['approval']['source']);
        $this->assertSame(0, $report['approval']['count']);
        foreach (range(1, 2) as $index) {
            OperationalAuditEvent::create([
                'entity_type' => 'swap', 'entity_id' => 190, 'action' => 'approved', 'occurred_at' => now(),
                'operation_id' => (string) Str::uuid(),
                'changes' => ['status' => ['before' => 'pending_manager', 'after' => 'approved']],
            ]);
        }
        $report = $this->diagnosis(exitCode: 1);
        $this->assertSame('multiple_approval_events', $report['approval']['source']);
        $this->assertSame(2, $report['approval']['count']);
        $this->assertFalse($report['review']['can_revert']);
    }

    public function test_later_changes_are_linked_to_the_actual_event_and_field_without_writes(): void
    {
        [, , , $shift] = $this->fixture();
        $shift->update(['start_time' => '08:00']);
        $before = [];
        foreach (['shift_swaps', 'shifts', 'operational_audit_events', 'shift_swap_reversals', 'notifications'] as $table) {
            $before[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $report = $this->diagnosis(exitCode: 1);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query['query']);
        }
        foreach ($before as $table => $contents) {
            $this->assertSame($contents, DB::table($table)->orderBy('id')->get()->toJson());
        }
        $this->assertSame(['expected' => '07:00', 'current' => '08:00'], $report['shifts'][0]['strict_differences']['start_time']);
        $this->assertNotEmpty($report['review']['later_events']);
        $this->assertContains('later_other_operation', array_column($report['shift_events'], 'relation_to_approval'));
    }

    public function test_partial_embedded_state_reproduces_existing_review_error_and_diagnostic_reports_it(): void
    {
        [$swap] = $this->fixture('partial_state');
        $review = app(ShiftSwapReversion::class)->inspect($swap);
        $this->assertFalse($review['can_revert']);
        $this->assertStringContainsString('evidência completa', implode(' ', $review['blocks']));
        $report = $this->diagnosis(exitCode: 1);
        $this->assertSame('embedded', $report['approval']['source']);
        $this->assertNull($report['review']['error']);
        $this->assertSame(['user_id'], $report['approval']['embedded_shape']['before'][1675]['missing_fields']);
        $this->assertFalse($report['review']['can_revert']);
        $this->assertDatabaseCount('shift_swap_reversals', 0);
    }

    public function test_actor_denial_stale_hash_and_production_guard_are_explicit(): void
    {
        [, , $professional] = $this->fixture();
        $report = $this->diagnosis(['--manager' => $professional->id], 1);
        $this->assertFalse($report['authorization']['reviewReversion']['allowed']);
        $report = $this->diagnosis(['--expected-snapshot' => str_repeat('0', 64)], 1);
        $this->assertTrue($report['review']['can_revert']);
        $this->assertFalse($report['review']['expected_snapshot_matches']);
        $this->artisan('app:diagnose-swap-reversion', ['id' => '190 OR 1=1'])->assertExitCode(2);
        $this->artisan('app:diagnose-swap-reversion', ['id' => 99999])->assertExitCode(2);
        $this->app['env'] = 'production';
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->artisan('app:diagnose-swap-reversion', ['id' => 190])->assertExitCode(2);
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }
}
