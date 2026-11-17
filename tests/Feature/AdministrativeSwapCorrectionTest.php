<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RectifyShiftSwapAction;
use App\Enums\ShiftSwapStatus;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\ShiftSwapRectification;
use App\Models\User;
use App\Notifications\ShiftSwapEventNotification;
use App\Services\ShiftSwapNotificationService;
use App\Support\AdministrativeSwapCorrection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AdministrativeSwapCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $mutual = true, bool $past = true, bool $legacy = false): array
    {
        $this->travelTo('2026-11-01 12:00:00');
        $manager = User::factory()->create(['role' => 'manager']);
        $profile = ['sector_id' => Sector::factory()->create()->id, 'category_id' => Category::factory()->create()->id];
        $requester = User::factory()->create($profile);
        $substitute = User::factory()->create($profile);
        $shift = Shift::factory()->create($profile + ['user_id' => $requester->id, 'date' => '2026-11-10', 'start_time' => '07:00', 'end_time' => '19:00', 'end_date' => '2026-11-10']);
        $counter = $mutual ? Shift::factory()->create($profile + ['user_id' => $substitute->id, 'date' => '2026-11-12', 'start_time' => '07:00', 'end_time' => '19:00', 'end_date' => '2026-11-12']) : null;
        $swap = ShiftSwap::factory()->pendingManager()->create(['shift_id' => $shift->id, 'substitute_shift_id' => $counter?->id, 'requester_id' => $requester->id, 'substitute_id' => $substitute->id]);
        if ($legacy) {
            // Synthetic pre-audit approval; never rewrite a real historical event.
            DB::table('shift_swaps')->where('id', $swap->id)->update(['status' => 'approved', 'approved_at' => now(), 'manager_id' => $manager->id]);
            $shift->update(['user_id' => $substitute->id]);
            $counter?->update(['user_id' => $requester->id]);
        } else {
            app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        }
        if ($past) {
            $this->travelTo('2026-11-16 12:00:00');
        }

        return [$manager, $swap->fresh(), $requester, $substitute];
    }

    private function payload(ShiftSwap $swap, array $overrides = []): array
    {
        return $overrides + ['administrativeKind' => 'rectification', 'administrativeReason' => 'Conferência institucional da ocorrência original.',
            'administrativeEvidence' => 'Conferidos os comprovantes originais e o histórico dos dois plantões.', 'administrativeConfirmed' => true,
            'administrativeSnapshot' => app(AdministrativeSwapCorrection::class)->review($swap->fresh())['fingerprint']];
    }

    public function test_simple_mutual_and_legacy_records_preserve_original_assignments_approval_quota_and_receipts(): void
    {
        foreach ([[false, false], [true, false], [true, true]] as [$mutual, $legacy]) {
            [$manager, $swap, $requester, $substitute] = $this->fixture($mutual, true, $legacy);
            $before = [];
            foreach (['shift_swaps', 'shifts', 'operational_audit_events', 'notifications'] as $table) {
                $before[$table] = DB::table($table)->orderBy('id')->get()->keyBy('id')->map(fn ($row) => (array) $row)->all();
            }
            $quota = [$requester->approvedSwapCount(), $substitute->approvedSwapCount()];
            $record = app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap));
            foreach ($before as $table => $rows) {
                $this->assertSame($rows, DB::table($table)->whereIn('id', array_keys($rows))->orderBy('id')->get()->keyBy('id')->map(fn ($row) => (array) $row)->all());
            }
            $this->assertSame($quota, [$requester->approvedSwapCount(), $substitute->approvedSwapCount()]);
            $this->assertSame(ShiftSwapStatus::Approved, $swap->fresh()->status);
            $this->assertSame($manager->id, $record->author_id);
            $this->assertSame('administratively_rectified', $record->receipt['event']);
            $this->assertSame('approved', $record->receipt['status']);
            $this->assertSame(0, DB::table('shift_swap_reversals')->count());
            $this->assertSame($record->id, OperationalAuditEvent::where('action', 'administratively_rectified')->where('entity_id', $swap->id)->sole()->changes['administrative_record_id']['after']);
            $notice = $requester->notifications()->latest()->first()->data;
            $this->assertSame($record->receipt, $notice['receipt']);
            $this->actingAs($requester)->get(route('notifications'))->assertOk()->assertSee('Retificada administrativamente');
            $this->get(route('swaps.rectification.receipt.pdf', $swap))->assertOk()->assertHeader('content-type', 'application/pdf');
        }
    }

    public function test_future_eligible_requests_must_keep_using_operational_reversion(): void
    {
        [$manager, $swap] = $this->fixture(past: false);
        $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap)), ValidationException::class);
        $this->assertDatabaseCount('shift_swap_rectifications', 0);
    }

    public function test_reason_evidence_explicit_confirmation_and_fresh_snapshot_are_mandatory(): void
    {
        [$manager, $swap] = $this->fixture();
        foreach ([['administrativeReason' => ' '], ['administrativeEvidence' => ' '], ['administrativeConfirmed' => false], ['administrativeSnapshot' => str_repeat('0', 64)], ['administrativeKind' => 'forced_reversal']] as $invalid) {
            $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap, $invalid)), ValidationException::class);
        }
        $payload = $this->payload($swap);
        $swap->shift->update(['start_time' => '08:00']);
        $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $payload), ValidationException::class);
        $this->assertDatabaseCount('shift_swap_rectifications', 0);
    }

    public function test_only_current_managers_can_register_and_participants_can_only_read_the_receipt(): void
    {
        [$manager, $swap, $professional] = $this->fixture();
        $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($professional, $swap->id, $this->payload($swap)), AuthorizationException::class);
        $staleManager = $manager->fresh();
        $manager->update(['role' => 'professional']);
        $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($staleManager, $swap->id, $this->payload($swap)), AuthorizationException::class);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('swaps.rectification.receipt.pdf', $swap))->assertForbidden();
    }

    public function test_notification_failure_rolls_back_record_audit_and_notifications(): void
    {
        [$manager, $swap] = $this->fixture();
        $before = [OperationalAuditEvent::count(), DB::table('notifications')->count()];
        $this->mock(ShiftSwapNotificationService::class)->shouldReceive('administrativelyRectified')->once()->andReturnUsing(function ($swap, $record): void {
            $swap->requester->notify(new ShiftSwapEventNotification(['title' => 'Registro administrativo', 'receipt' => $record->receipt]));
            throw new \RuntimeException('Second notification unavailable');
        });
        $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap)), \RuntimeException::class);
        $this->assertDatabaseCount('shift_swap_rectifications', 0);
        $this->assertSame($before, [OperationalAuditEvent::count(), DB::table('notifications')->count()]);
    }

    public function test_duplicate_registration_and_all_model_query_and_raw_mutations_are_refused(): void
    {
        [$manager, $swap] = $this->fixture();
        $record = app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap, ['administrativeKind' => 'annulment']));
        $original = $record->fresh()->getRawOriginal();
        $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap)), AuthorizationException::class);
        $this->assertThrows(fn () => $record->update(['reason' => 'Alterada']), \LogicException::class);
        $this->assertThrows(fn () => ShiftSwapRectification::query()->whereKey($record->id)->delete(), \LogicException::class);
        foreach (['update', 'delete'] as $operation) {
            $this->assertThrows(fn () => DB::transaction(fn () => $operation === 'update'
                ? DB::table('shift_swap_rectifications')->where('id', $record->id)->update(['reason' => 'Alterada'])
                : DB::table('shift_swap_rectifications')->where('id', $record->id)->delete()), QueryException::class);
        }
        $this->assertSame($original, $record->fresh()->getRawOriginal());
    }

    public function test_mobile_flow_requires_confirmation_and_displays_the_actual_administrative_operation(): void
    {
        [$manager, $swap] = $this->fixture();
        Livewire::actingAs($manager)->test(SwapApprovals::class)->call('requestRevert', $swap->id)
            ->call('analyzeAdministrativeCorrection')->call('confirmAdministrativeCorrection')
            ->assertHasErrors(['administrativeReason', 'administrativeEvidence', 'administrativeConfirmed'])
            ->set('administrativeReason', 'Ocorrência revisada pela instituição.')
            ->set('administrativeEvidence', 'Conferidos os comprovantes e eventos apresentados pelos profissionais.')
            ->set('administrativeConfirmed', true)->call('confirmAdministrativeCorrection')->assertHasNoErrors()
            ->assertSet('pendingRevertSwapId', null)->assertSee('Retificada administrativamente')->assertSee('Aprovada');
        $this->assertDatabaseCount('shift_swap_rectifications', 1);
    }

    public function test_nonapproved_requests_and_destructive_rollback_are_denied(): void
    {
        [$manager, $swap] = $this->fixture();
        foreach (['pending_manager', 'rejected', 'reverted'] as $status) {
            $other = ShiftSwap::factory()->create(['status' => $status]);
            $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $other->id, $this->payload($other)), AuthorizationException::class);
        }
        app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap));
        $migration = require database_path('migrations/2026_11_16_000000_create_shift_swap_rectifications_table.php');
        $this->assertThrows(fn () => $migration->down(), \LogicException::class);
        $this->assertDatabaseCount('shift_swap_rectifications', 1);
    }

    public function test_refresh_preserves_notes_but_requires_confirmation_again(): void
    {
        [$manager, $swap] = $this->fixture();
        Livewire::actingAs($manager)->test(SwapApprovals::class)->call('requestRevert', $swap->id)
            ->call('analyzeAdministrativeCorrection')->set('administrativeReason', 'Notas administrativas preservadas.')
            ->set('administrativeEvidence', 'Referências documentais conferidas.')
            ->set('administrativeConfirmed', true)->call('refreshReversion')
            ->assertSet('administrativeConfirmed', false)->assertSet('administrativeSnapshot', null)
            ->call('analyzeAdministrativeCorrection')->assertSet('administrativeConfirmed', false)
            ->assertSet('administrativeReason', 'Notas administrativas preservadas.');
        $this->assertDatabaseCount('shift_swap_rectifications', 0);
    }

    public function test_failure_to_append_audit_rolls_back_the_administrative_record(): void
    {
        [$manager, $swap] = $this->fixture();
        $dispatcher = OperationalAuditEvent::getEventDispatcher();
        OperationalAuditEvent::setEventDispatcher(clone $dispatcher);
        $before = [OperationalAuditEvent::count(), DB::table('notifications')->count()];
        try {
            OperationalAuditEvent::creating(function ($event): void {
                if ($event->action === 'administratively_rectified') {
                    throw new \RuntimeException('Audit unavailable');
                }
            });
            $this->assertThrows(fn () => app(RectifyShiftSwapAction::class)->execute($manager, $swap->id, $this->payload($swap)), \RuntimeException::class);
        } finally {
            OperationalAuditEvent::setEventDispatcher($dispatcher);
        }
        $this->assertDatabaseCount('shift_swap_rectifications', 0);
        $this->assertSame($before, [OperationalAuditEvent::count(), DB::table('notifications')->count()]);
    }
}
