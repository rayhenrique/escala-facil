<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\AuditHistory;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Absence;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\ShiftSwapReversal;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditContext;
use App\Support\ShiftSwapReceipt;
use App\Support\ShiftSwapReversion;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class SafeSwapReversionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo('2026-10-11 12:00:00');
    }

    private function approved(bool $mutual = true, bool $legacy = false): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $requester = User::factory()->create(['role' => UserRole::Professional, 'sector_id' => Sector::factory()->create()->id, 'category_id' => Category::factory()->create()->id]);
        $substitute = User::factory()->create(['sector_id' => $requester->sector_id, 'category_id' => $requester->category_id]);
        $data = ['user_id' => $requester->id, 'sector_id' => $requester->sector_id, 'category_id' => $requester->category_id,
            'date' => '2026-10-31', 'start_time' => '07:00', 'end_time' => '07:00', 'end_date' => '2026-11-01', 'status' => ShiftStatus::Scheduled];
        $shift = Shift::factory()->create($data);
        $counter = $mutual ? Shift::factory()->create(array_replace($data, ['user_id' => $substitute->id, 'date' => '2026-11-03', 'end_date' => '2026-11-04'])) : null;
        $swap = ShiftSwap::factory()->pendingManager()->create(['shift_id' => $shift->id, 'substitute_shift_id' => $counter?->id,
            'requester_id' => $requester->id, 'substitute_id' => $substitute->id]);
        if ($legacy) {
            app(OperationalAuditContext::class)->transaction($manager, function () use ($swap, $shift, $counter, $manager, $requester, $substitute) {
                DB::table('shift_swaps')->where('id', $swap->id)->update(['status' => 'approved', 'manager_id' => $manager->id]);
                OperationalAuditEvent::query()->create(['entity_type' => 'swap', 'entity_id' => $swap->id, 'action' => 'approved',
                    'author_id' => $manager->id, 'occurred_at' => now(), 'operation_id' => app(OperationalAuditContext::class)->operationId(),
                    'changes' => ['status' => ['before' => 'pending_manager', 'after' => 'approved']]]);
                $shift->update(['user_id' => $substitute->id]);
                $counter?->update(['user_id' => $requester->id]);
            });
        } else {
            app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        }

        return [$manager, $requester, $substitute, $shift->fresh(), $counter?->fresh(), $swap->fresh()];
    }

    public function test_review_windows_are_exclusive_and_refresh_preserves_administrative_reason(): void
    {
        [$manager, , , , , $swap] = $this->approved();
        $screen = Livewire::actingAs($manager)->test(SwapApprovals::class)
            ->call('openSwapAnalysis', $swap->id)
            ->call('requestRevert', $swap->id)
            ->assertSet('pendingDecisionSwapId', null)
            ->assertSet('pendingRevertSummary.status_label', 'Aprovada')
            ->assertSee($swap->requester->name)->assertSee($swap->substitute->name)
            ->set('reversionReason', 'Correção administrativa solicitada.')
            ->call('refreshReversion')->assertSet('reversionReason', 'Correção administrativa solicitada.');
        $this->assertDatabaseCount('shift_swap_reversals', 0);
        $screen->call('openSwapAnalysis', $swap->id)->assertSet('pendingRevertSwapId', null)
            ->assertSet('pendingDecisionSummary.status_label', 'Aprovada');
        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
    }

    public function test_mobile_reversion_keeps_the_review_open_when_assignment_changes_after_preview(): void
    {
        [$manager, $requester, , $shift, , $swap] = $this->approved();
        $screen = Livewire::actingAs($manager)->test(SwapApprovals::class)
            ->call('requestRevert', $swap->id)->set('reversionReason', 'Corrigir atribuições originais.');
        $shift->update(['user_id' => $requester->id]);
        $screen->call('confirmRevert')->assertHasErrors('reversion')
            ->assertSet('pendingRevertSwapId', $swap->id)
            ->assertSet('reversionReason', 'Corrigir atribuições originais.')
            ->call('refreshReversion')->assertDontSee('Confirmar desfazer')
            ->assertSee('Analisar correção administrativa');
        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
        $this->assertDatabaseCount('shift_swap_reversals', 0);
    }

    private function review(ShiftSwap $swap): array
    {
        return app(ShiftSwapReversion::class)->inspect($swap->fresh());
    }

    private function revert(User $manager, ShiftSwap $swap, ?string $snapshot = null): ShiftSwap
    {
        return app(RevertShiftSwapAction::class)->execute($manager, $swap->id, 'Revisão administrativa acordada com os profissionais.', $snapshot ?? $this->review($swap)['fingerprint']);
    }

    public function test_mutual_24_hour_reversion_restores_both_originals_and_keeps_original_receipts(): void
    {
        [$manager, $requester, $substitute, $shift, $counter, $swap] = $this->approved();
        $notifications = DB::table('notifications')->orderBy('id')->get()->toArray();
        $approval = OperationalAuditEvent::query()->where('action', 'approved')->firstOrFail();
        $originalApproval = $approval->getAttributes();
        $review = $this->review($swap);
        $this->assertTrue($review['can_revert'], implode(' ', $review['blocks']));
        $this->assertCount(2, $review['rows']);
        $this->assertStringContainsString('24h', $review['rows'][0]['period']);
        $this->assertSame($requester->name, $review['rows'][0]['after']);
        $this->assertSame($substitute->name, $review['rows'][1]['after']);
        $this->assertTrue(Gate::forUser($manager)->allows('revert', $swap));
        $this->revert($manager, $swap, $review['fingerprint']);
        $this->assertSame($requester->id, $shift->fresh()->user_id);
        $this->assertSame($substitute->id, $counter->fresh()->user_id);
        $this->assertSame('2026-11-01', $shift->fresh()->end_date->toDateString());
        $this->assertEquals($originalApproval, $approval->fresh()->getAttributes());
        foreach ($notifications as $original) {
            $this->assertEquals($original, DB::table('notifications')->find($original->id));
        }
        $record = ShiftSwapReversal::query()->sole();
        $this->assertSame($manager->id, $record->author_id);
        $this->assertSame($approval->id, $record->approval_event_id);
        $this->assertSame($substitute->id, $record->before[$shift->id]['user_id']);
        $this->assertSame($requester->id, $record->after[$shift->id]['user_id']);
        $this->assertSame(3, OperationalAuditEvent::query()->where('operation_id', $record->operation_id)->count());
        $this->assertSame(4, DB::table('notifications')->count());
        $receipt = app(ShiftSwapReceipt::class)->current($swap->fresh());
        $this->assertStringContainsString('plantão #'.$shift->id, $receipt['requester_movement']);
        $this->assertStringContainsString('plantão #'.$counter->id, $receipt['substitute_movement']);
        $this->assertSame($record->reason, $receipt['reversion_reason']);
        $html = view('reports.swap-receipt', ['receipt' => $receipt, 'generatedAt' => now()->format('d/m/Y H:i')])->render();
        $this->assertStringContainsString('Plantão de Contrapartida', $html);
        preg_match_all('/<table\b[^>]*>.*?<\/table>/s', $html, $tables);
        foreach ($tables[0] as $table) {
            $this->assertStringNotContainsString('class="movement-item"', $table);
        }
        $this->actingAs($manager)->get(route('swaps.receipt.pdf', $swap))->assertOk();
        Livewire::test(AuditHistory::class, ['entity' => 'swap', 'record' => (string) $swap->id])->assertSee($record->reason);
    }

    public function test_complete_legacy_audit_trail_can_restore_a_simple_swap(): void
    {
        [$manager, $requester, $substitute, $shift, $counter, $swap] = $this->approved(false, true);
        $this->assertTrue($this->review($swap)['can_revert']);
        $this->revert($manager, $swap);
        $this->assertSame($requester->id, $shift->fresh()->user_id);
        $this->assertSame(ShiftSwapStatus::Reverted, $swap->fresh()->status);
        $this->assertCount(1, ShiftSwapReversal::all());
    }

    public function test_past_and_started_swaps_offer_read_only_administrative_analysis(): void
    {
        [$manager, , , $shift, , $swap] = $this->approved();
        $this->travelTo('2026-10-31 07:00:00');
        $screen = Livewire::actingAs($manager)->test(SwapApprovals::class)->call('showHistory')
            ->call('requestRevert', $swap->id)->assertSee('já começou')->assertDontSee('Confirmar desfazer')
            ->call('analyzeAdministrativeCorrection')->assertSee('Conferência administrativa separada')
            ->assertSeeHtml('entity=shift')->assertSee('somente para consulta')->call('cancelRevert');
        $this->assertFalse($this->review($swap)['can_revert']);
        $this->assertThrows(fn () => $this->revert($manager, $swap), ValidationException::class);
        $this->travelTo('2026-11-05 12:00:00');
        $this->assertFalse($this->review($swap)['can_revert']);
        $this->assertDatabaseCount('shift_swap_reversals', 0);
    }

    public function test_counterpart_absence_and_pending_dependency_block_both_legs(): void
    {
        [$manager, , , $shift, $counter, $swap] = $this->approved();
        Absence::factory()->create(['shift_id' => $counter->id]);
        $pending = ShiftSwap::factory()->create(['substitute_shift_id' => $counter->id, 'status' => ShiftSwapStatus::PendingProfessional]);
        $review = $this->review($swap);
        $this->assertStringContainsString('ausência', implode(' ', $review['blocks']));
        $this->assertStringContainsString('#'.$pending->id, implode(' ', $review['blocks']));
        $this->assertThrows(fn () => $this->revert($manager, $swap), ValidationException::class);
        $this->assertSame($swap->substitute_id, $shift->fresh()->user_id);
        $this->assertSame($swap->requester_id, $counter->fresh()->user_id);
    }

    public function test_later_reallocation_even_restored_and_chained_swap_require_manual_analysis(): void
    {
        [$manager, $requester, $substitute, $shift, $counter, $swap] = $this->approved();
        $counter->update(['user_id' => $substitute->id]);
        $counter->update(['user_id' => $requester->id]);
        $this->assertStringContainsString('alterações posteriores', implode(' ', $this->review($swap)['blocks']));
        $this->assertThrows(fn () => $this->revert($manager, $swap), ValidationException::class);
        $third = User::factory()->create(['category_id' => $requester->category_id, 'sector_id' => $requester->sector_id]);
        $chain = ShiftSwap::factory()->pendingManager()->create(['shift_id' => $shift->id, 'requester_id' => $substitute->id, 'substitute_id' => $third->id]);
        app(DecideShiftSwapAction::class)->approve($manager, $chain->id);
        $this->assertStringContainsString('atribuição atual', implode(' ', $this->review($swap)['blocks']));
        $this->assertThrows(fn () => $this->revert($manager, $swap), ValidationException::class);
        $this->assertSame($third->id, $shift->fresh()->user_id);
    }

    public function test_conflict_across_midnight_blocks_and_consecutive_shift_is_only_warning(): void
    {
        [$manager, $requester, , $shift, , $swap] = $this->approved(false);
        $other = Shift::factory()->create(['user_id' => $requester->id, 'category_id' => $requester->category_id,
            'sector_id' => $requester->sector_id, 'date' => '2026-11-01', 'start_time' => '07:00', 'end_time' => '19:00', 'status' => ShiftStatus::Scheduled]);
        $review = $this->review($swap);
        $this->assertTrue($review['can_revert']);
        $this->assertNotEmpty($review['warnings']);
        $other->update(['start_time' => '06:59']);
        $review = $this->review($swap);
        $this->assertFalse($review['can_revert']);
        $this->assertStringContainsString('Conflito com o plantão #'.$other->id, implode(' ', $review['blocks']));
        $this->assertThrows(fn () => $this->revert($manager, $swap), ValidationException::class);
    }

    public function test_stale_preview_cannot_restore_after_any_counterpart_change(): void
    {
        [$manager, , , $shift, $counter, $swap] = $this->approved();
        $snapshot = $this->review($swap)['fingerprint'];
        $counter->update(['start_time' => '08:00']);
        $this->assertThrows(fn () => $this->revert($manager, $swap, $snapshot), ValidationException::class);
        $this->assertSame($swap->substitute_id, $shift->fresh()->user_id);
        $this->assertSame($swap->requester_id, $counter->fresh()->user_id);
        $this->assertDatabaseCount('shift_swap_reversals', 0);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_server_revalidates_professional_eligibility_and_manager_role(): void
    {
        [$manager, $requester, , $shift, , $swap] = $this->approved();
        $snapshot = $this->review($swap)['fingerprint'];
        $requester->update(['requires_activation' => true]);
        $this->assertThrows(fn () => $this->revert($manager, $swap, $snapshot), ValidationException::class);
        $manager->update(['role' => UserRole::Professional]);
        $this->assertThrows(fn () => $this->revert($manager, $swap), AuthorizationException::class);
        $this->assertSame($swap->substitute_id, $shift->fresh()->user_id);
    }

    public function test_reason_snapshot_and_locked_component_identifiers_are_required(): void
    {
        [$manager, , , , , $swap] = $this->approved(false);
        $screen = Livewire::actingAs($manager)->test(SwapApprovals::class)->call('requestRevert', $swap->id)
            ->call('confirmRevert')->assertHasErrors('reversionReason');
        $this->assertThrows(fn () => app(RevertShiftSwapAction::class)->execute($manager, $swap->id, '   ', $this->review($swap)['fingerprint']), ValidationException::class);
        $this->assertThrows(fn () => app(RevertShiftSwapAction::class)->execute($manager, $swap->id, 'Justificativa válida.'), ValidationException::class);
        $this->assertThrows(fn () => $screen->set('pendingRevertSwapId', 999), CannotUpdateLockedPropertyException::class);
        $this->assertThrows(fn () => $screen->set('reversionSnapshot', str_repeat('a', 64)), CannotUpdateLockedPropertyException::class);
    }

    public function test_notification_failure_rolls_back_both_assignments_and_reversal_audit(): void
    {
        [$manager, , , $shift, $counter, $swap] = $this->approved();
        $events = OperationalAuditEvent::count();
        $this->mock(ShiftSwapNotificationService::class)->shouldReceive('reverted')->once()->andThrow(new \RuntimeException('Notification unavailable'));
        $this->assertThrows(fn () => $this->revert($manager, $swap), \RuntimeException::class);
        $this->assertSame($swap->substitute_id, $shift->fresh()->user_id);
        $this->assertSame($swap->requester_id, $counter->fresh()->user_id);
        $this->assertSame(ShiftSwapStatus::Approved, $swap->fresh()->status);
        $this->assertSame($events, OperationalAuditEvent::count());
        $this->assertDatabaseCount('shift_swap_reversals', 0);
    }

    public function test_second_confirmation_is_refused_and_reversal_is_immutable_in_model_and_database(): void
    {
        [$manager, , , , , $swap] = $this->approved();
        $snapshot = $this->review($swap)['fingerprint'];
        $this->revert($manager, $swap, $snapshot);
        $this->assertThrows(fn () => $this->revert($manager, $swap, $snapshot), ValidationException::class);
        $record = ShiftSwapReversal::query()->sole();
        $this->assertThrows(fn () => $record->update(['reason' => 'Alterada']), \LogicException::class);
        $this->assertThrows(fn () => ShiftSwapReversal::query()->whereKey($record->id)->delete(), \LogicException::class);
        foreach (['update', 'delete'] as $operation) {
            $this->assertThrows(fn () => DB::transaction(fn () => $operation === 'update'
                ? DB::table('shift_swap_reversals')->where('id', $record->id)->update(['reason' => 'Alterada'])
                : DB::table('shift_swap_reversals')->where('id', $record->id)->delete()), QueryException::class);
        }
        $this->assertDatabaseCount('shift_swap_reversals', 1);
        $this->assertDatabaseCount('notifications', 4);
    }

    public function test_missing_approval_provenance_and_modified_legacy_bindings_cannot_be_restored(): void
    {
        [$manager, $requester, $substitute, $shift, , $swap] = $this->approved(false, true);
        $newOriginal = User::factory()->create(['category_id' => $requester->category_id, 'sector_id' => $requester->sector_id]);
        $swap->update(['requester_id' => $newOriginal->id]);
        $this->assertFalse($this->review($swap)['can_revert']);
        $this->assertThrows(fn () => $this->revert($manager, $swap), ValidationException::class);
        $missing = ShiftSwap::factory()->approved()->create(['shift_id' => $shift->id, 'requester_id' => $requester->id, 'substitute_id' => $substitute->id]);
        $this->assertStringContainsString('evidência completa', implode(' ', $this->review($missing)['blocks']));
        $this->assertSame($substitute->id, $shift->fresh()->user_id);
    }
}
