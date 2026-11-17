<?php

namespace Tests\Feature;

use App\Models\OperationalAuditEvent;
use App\Models\ShiftSwap;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonthlySwapQuotaDiagnosisTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.timezone' => 'America/Maceio']);
        $this->travelTo(CarbonImmutable::parse('2026-11-16 12:00:00', 'America/Maceio'));
    }

    private function historicalSwap(array $attributes = []): ShiftSwap
    {
        return ShiftSwap::factory()->approved()->create($attributes + [
            'approved_at' => '2026-10-31 23:59:59', 'created_at' => '2026-09-10 10:00:00', 'updated_at' => '2026-11-10 10:00:00',
        ]);
    }

    private function approval(ShiftSwap $swap, string $date = '2026-10-31 23:59:59.123456', array $changes = []): void
    {
        OperationalAuditEvent::create([
            'entity_type' => 'swap', 'entity_id' => $swap->id, 'action' => 'approved', 'occurred_at' => $date,
            'operation_id' => (string) Str::uuid(),
            'changes' => $changes + ['status' => ['before' => 'pending_manager', 'after' => 'approved']],
        ]);
    }

    private function diagnose(array $options = [], int $exitCode = 1): array
    {
        $this->assertSame($exitCode, Artisan::call('app:diagnose-monthly-swap-quota', $options + ['--jsonl' => true]));

        return array_map(fn ($line) => json_decode($line, true, flags: JSON_THROW_ON_ERROR), explode("\n", trim(Artisan::output())));
    }

    public function test_diagnosis_is_read_only_and_reports_unprovable_backfill_without_replacing_dates(): void
    {
        $updated = $this->historicalSwap(['approved_at' => '2026-11-10 10:00:00']);
        $created = $this->historicalSwap(['approved_at' => '2026-09-10 10:00:00', 'updated_at' => null]);
        $missing = $this->historicalSwap();
        DB::table('shift_swaps')->where('id', $missing->id)->update(['approved_at' => null]);
        ShiftSwap::factory()->pendingManager()->create();
        $before = [DB::table('shift_swaps')->orderBy('id')->get()->toJson(), DB::table('operational_audit_events')->orderBy('id')->get()->toJson()];
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $rows = $this->diagnose();
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
        }
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^\s*(insert|update|delete|replace|alter|create|drop|truncate)\b/i', $query['query']);
        }
        $this->assertSame($before, [DB::table('shift_swaps')->orderBy('id')->get()->toJson(), DB::table('operational_audit_events')->orderBy('id')->get()->toJson()]);
        $findings = collect($rows)->where('type', 'finding')->keyBy('swap_id');
        $this->assertContains('possible_updated_at_backfill', $findings[$updated->id]['issues']);
        $this->assertContains('possible_created_at_backfill', $findings[$created->id]['issues']);
        $this->assertContains('missing_or_invalid_approved_at', $findings[$missing->id]['issues']);
        $this->assertSame(['type' => 'summary', 'checked' => 4, 'divergent' => 1, 'uncertain' => 2], end($rows));
    }

    public function test_old_event_corroborates_second_precision_even_after_later_updates(): void
    {
        $swap = $this->historicalSwap();
        $this->approval($swap);
        $rows = $this->diagnose(exitCode: 0);
        $this->assertSame(['type' => 'summary', 'checked' => 1, 'divergent' => 0, 'uncertain' => 0], end($rows));
    }

    public function test_a_legacy_date_in_another_month_is_uncertain_not_an_automatic_correction(): void
    {
        $swap = $this->historicalSwap(['approved_at' => '2026-11-10 10:00:00']);
        $this->approval($swap);
        $rows = $this->diagnose();
        $this->assertSame('uncertain', $rows[1]['classification']);
        $this->assertContains('approval_month_mismatch', $rows[1]['issues']);
        $this->assertSame('2026-11', $rows[1]['quota_month']);
        $this->assertSame('2026-11-10 10:00:00', $swap->fresh()->getRawOriginal('approved_at'));
    }

    public function test_explicit_audit_distinguishes_a_divergent_date_from_an_event_crossing_midnight(): void
    {
        $correct = $this->historicalSwap();
        $changed = $this->historicalSwap(['approved_at' => '2026-11-10 10:00:00']);
        foreach ([$correct, $changed] as $swap) {
            $this->approval($swap, '2026-11-01 00:00:00.000001', ['approved_at' => ['before' => null, 'after' => '2026-10-31T23:59:59-03:00']]);
        }
        $rows = $this->diagnose();
        $this->assertSame($changed->id, $rows[1]['swap_id']);
        $this->assertSame(['audited_approved_at_mismatch'], $rows[1]['issues']);
        $this->assertSame(['type' => 'summary', 'checked' => 2, 'divergent' => 1, 'uncertain' => 0], end($rows));
    }

    public function test_reverted_approval_is_preserved_and_never_reported_as_consuming_quota(): void
    {
        $swap = $this->historicalSwap(['status' => 'reverted']);
        $rows = $this->diagnose();
        $this->assertNull($rows[1]['quota_month']);
        $this->assertSame('2026-10-31 23:59:59', $swap->fresh()->getRawOriginal('approved_at'));
        $this->assertSame(0, $swap->requester->approvedSwapCount(10, 2026));
    }

    public function test_multiple_or_malformed_events_are_uncertain_and_unexpected_status_is_divergent(): void
    {
        $multiple = $this->historicalSwap();
        $this->approval($multiple);
        $this->approval($multiple);
        $malformed = $this->historicalSwap();
        $this->approval($malformed, changes: ['approved_at' => ['after' => ['invalid']]]);
        $unexpected = $this->historicalSwap(['status' => 'rejected']);
        $rows = collect($this->diagnose())->where('type', 'finding')->keyBy('swap_id');
        $this->assertContains('multiple_approval_events', $rows[$multiple->id]['issues']);
        $this->assertContains('invalid_audited_approved_at', $rows[$malformed->id]['issues']);
        $this->assertContains('unexpected_approval_for_status', $rows[$unexpected->id]['issues']);
    }

    public function test_filter_and_input_validation_do_not_change_the_timezone_or_data(): void
    {
        $selected = $this->historicalSwap();
        $this->historicalSwap();
        $rows = $this->diagnose(['--swap' => [(string) $selected->id]]);
        $this->assertSame(1, end($rows)['checked']);
        $this->artisan('app:diagnose-monthly-swap-quota', ['--swap' => ['1 OR 1=1']])->assertExitCode(2);
        config(['app.timezone' => 'UTC']);
        $this->artisan('app:diagnose-monthly-swap-quota')->assertExitCode(2);
        $this->assertSame('UTC', config('app.timezone'));
    }

    public function test_impossible_chronology_is_reported_and_text_output_includes_the_original_date(): void
    {
        $future = $this->historicalSwap(['approved_at' => '2026-11-20 10:00:00', 'updated_at' => '2026-11-20 10:00:00']);
        $beforeCreation = $this->historicalSwap(['approved_at' => '2026-09-09 10:00:00']);
        $afterUpdate = $this->historicalSwap(['approved_at' => '2026-11-11 10:00:00']);
        $rows = collect($this->diagnose())->where('type', 'finding')->keyBy('swap_id');
        $this->assertContains('approval_in_future', $rows[$future->id]['issues']);
        $this->assertContains('approval_before_creation', $rows[$beforeCreation->id]['issues']);
        $this->assertContains('approval_after_updated_at', $rows[$afterUpdate->id]['issues']);
        $this->artisan('app:diagnose-monthly-swap-quota', ['--swap' => [$future->id]])
            ->expectsOutputToContain('approved_at=2026-11-20 10:00:00')->assertExitCode(1);
    }

    public function test_an_empty_database_has_no_findings(): void
    {
        $rows = $this->diagnose(exitCode: 0);
        $this->assertSame(['type' => 'summary', 'checked' => 0, 'divergent' => 0, 'uncertain' => 0], end($rows));
    }
}
