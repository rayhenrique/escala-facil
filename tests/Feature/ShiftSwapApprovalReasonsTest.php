<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Livewire\Manager\SwapApprovals;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapAnalysis;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftSwapApprovalReasonsTest extends TestCase
{
    use RefreshDatabase;

    private function mutual(): array
    {
        $sector = Sector::factory()->create();
        $category = Category::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $requester = User::factory()->create(['name' => 'Solicitante QA', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $substitute = User::factory()->create(['name' => 'Substituta QA', 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $attributes = ['sector_id' => $sector->id, 'category_id' => $category->id, 'start_time' => '07:00', 'end_time' => '07:01'];
        $shift = Shift::factory()->create($attributes + ['date' => '2026-10-15', 'user_id' => $requester->id]);
        $counterpart = Shift::factory()->create($attributes + ['date' => '2026-10-14', 'user_id' => $substitute->id]);
        $swap = ShiftSwap::factory()->pendingManager()->create(['id' => 89, 'shift_id' => $shift->id, 'substitute_shift_id' => $counterpart->id,
            'requester_id' => $requester->id, 'substitute_id' => $substitute->id, 'created_at' => '2026-10-10 12:00:00']);

        return [$manager, $swap];
    }

    private function participation(User $participant, string $created = '2026-10-01 00:00:00', ShiftSwapStatus $status = ShiftSwapStatus::Approved, bool $asSubstitute = false): ShiftSwap
    {
        $other = User::factory()->create();
        $shift = Shift::factory()->create(['date' => '2026-10-01', 'user_id' => $participant->id]);

        return ShiftSwap::factory()->create(['shift_id' => $shift->id, 'requester_id' => $asSubstitute ? $other->id : $participant->id,
            'substitute_id' => $asSubstitute ? $participant->id : $other->id, 'status' => $status, 'created_at' => $created]);
    }

    public function test_synthetic_request_89_with_one_and_three_participations_is_approved(): void
    {
        [$manager, $swap] = $this->mutual();
        $this->participation($swap->requester);
        for ($i = 0; $i < 3; $i++) {
            $this->participation($swap->substitute, asSubstitute: $i % 2 === 0);
        }
        $analysis = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager);
        $this->assertSame([], $analysis['approval_blockers']);
        $this->assertSame([], $analysis['warnings']);
        $this->assertTrue($analysis['can_approve']);
        $this->assertTrue(Gate::forUser($manager)->allows('approve', $swap));
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', 89)
            ->assertSee('3 de 4')->assertSee('Esta aprovação acrescenta 1 participação: 4 de 4.')
            ->assertSet('pendingDecisionSummary.can_approve', true)
            ->call('requestDecision', 89, 'approve')->call('confirmDecision')->assertHasNoErrors();
        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
        $this->assertSame($swap->substitute_id, $swap->shift->refresh()->user_id);
        $this->assertSame($swap->requester_id, $swap->substituteShift->refresh()->user_id);
        $this->assertSame(2, $swap->requester->approvedSwapCount(10, 2026));
        $this->assertSame(4, $swap->substitute->approvedSwapCount(10, 2026));
        $this->assertDatabaseCount('notifications', 2);
        $closed = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager);
        $this->assertSame(['request_status'], array_column($closed['approval_blockers'], 'code'));
    }

    public function test_full_quota_names_the_participant_month_and_count_in_both_ui_modules(): void
    {
        [$manager, $swap] = $this->mutual();
        for ($i = 0; $i < 4; $i++) {
            $this->participation($swap->substitute);
        }
        $response = Gate::forUser($manager)->inspect('approve', $swap->fresh());
        $this->assertFalse($response->allowed());
        $this->assertStringContainsString('Substituta QA: 4 de 4', $response->message());
        foreach ([Dashboard::class, SwapApprovals::class] as $component) {
            Livewire::actingAs($manager)->test($component)->call('openSwapAnalysis', 89)
                ->assertSet('pendingDecisionSummary.can_approve', false)->assertSet('pendingDecisionSummary.can_reject', true)
                ->assertSee('Limite mensal atingido por Substituta QA: 4 de 4')->assertSee('10/2026')
                ->call('requestDecision', 89, 'approve')->assertHasErrors('decision')->assertSee('Atualizar análise')
                ->call('requestDecision', 89, 'reject')->assertHasNoErrors()->assertSet('pendingDecision', 'reject');
        }
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_monthly_count_uses_request_creation_and_excludes_other_states_and_months(): void
    {
        [$manager, $swap] = $this->mutual();
        foreach (['2026-10-01 00:00:00', '2026-10-31 23:59:59'] as $date) {
            $this->participation($swap->requester, $date);
        }
        $mutual = $this->participation($swap->requester, asSubstitute: true);
        $mutual->update(['substitute_shift_id' => $swap->substitute_shift_id]);
        $this->participation($swap->requester, '2026-09-30 23:59:59');
        $this->participation($swap->requester, '2026-11-01 00:00:00');
        foreach ([ShiftSwapStatus::PendingProfessional, ShiftSwapStatus::PendingManager, ShiftSwapStatus::Rejected, ShiftSwapStatus::Reverted] as $status) {
            $this->participation($swap->requester, status: $status);
        }
        $this->assertSame(3, $swap->requester->approvedSwapCount(10, 2026));
        $this->assertFalse($swap->requester->hasReachedSwapLimit(10, 2026));
        $swap->shift->update(['date' => '2026-11-15']);
        $this->assertTrue(app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager)['can_approve']);
    }

    public function test_both_modified_assignments_have_specific_independent_blockers(): void
    {
        [$manager, $swap] = $this->mutual();
        $swap->shift->update(['user_id' => null]);
        $swap->substituteShift->update(['user_id' => $swap->requester_id]);
        $analysis = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager);
        $this->assertSame(['shift_assignment', 'substituteShift_assignment'], array_column($analysis['approval_blockers'], 'code'));
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', 89)
            ->assertSee('Plantão original #'.$swap->shift_id.': atribuição modificada.')
            ->assertSee('Plantão de contrapartida #'.$swap->substitute_shift_id.': atribuição modificada.')
            ->assertSee('atual sem profissional')->assertSet('pendingDecisionSummary.can_approve', false)
            ->assertDontSee('Confira a atribuição dos plantões e o limite mensal.');
        $this->expectException(AuthorizationException::class);
        app(DecideShiftSwapAction::class)->approve($manager, 89);
    }

    public function test_each_incompatible_shift_status_is_named(): void
    {
        [$manager, $swap] = $this->mutual();
        $swap->shift->update(['status' => ShiftStatus::Uncovered]);
        $swap->substituteShift->update(['status' => ShiftStatus::AbsenceReported]);
        $analysis = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager);
        $this->assertSame(['shift_status', 'substituteShift_status'], array_column($analysis['approval_blockers'], 'code'));
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', 89)
            ->assertSee('situação incompatível (Descoberto)')->assertSee('situação incompatível (Ausência informada)');
    }

    public function test_already_decided_request_can_be_reviewed_but_cannot_be_decided_again(): void
    {
        [$manager, $swap] = $this->mutual();
        app(DecideShiftSwapAction::class)->reject($manager, 89, 'Manter escala original.');
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', 89)
            ->assertSee('Pedido #89: situação incompatível (Rejeitada)')
            ->assertDontSee('Esta aprovação acrescenta')
            ->assertSet('pendingDecisionSummary.can_approve', false)->assertSet('pendingDecisionSummary.can_reject', false);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_missing_relations_produce_blockers_instead_of_crashing_the_analysis(): void
    {
        [$manager, $swap] = $this->mutual();
        $swap->setRelation('shift', null)->setRelation('substituteShift', null)->setRelation('requester', null)->setRelation('substitute', null);
        $analysis = app(ShiftSwapAnalysis::class)->make($swap, $manager);
        $this->assertSame(['requester_missing', 'substitute_missing', 'shift_missing', 'substituteShift_missing'], array_column($analysis['approval_blockers'], 'code'));
        $this->assertFalse($analysis['can_approve']);
        $this->assertSame([], $analysis['warnings']);
    }

    public function test_irrelevant_account_changes_do_not_invalidate_an_approval(): void
    {
        [$manager, $swap] = $this->mutual();
        $before = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager)['fingerprint'];
        $swap->requester->update(['last_seen_version' => '1.32.0', 'remember_token' => 'new-session-token', 'password' => 'Changed-QA-password']);
        $swap->substitute->update(['last_seen_version' => '1.32.1']);
        $after = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager)['fingerprint'];
        $this->assertSame($before, $after);
        $this->assertSame(ShiftSwapStatus::Approved, app(DecideShiftSwapAction::class)->approve($manager, 89, $before)->status);
    }

    public function test_changed_counterpart_explains_stale_review_and_refreshes_without_a_decision(): void
    {
        [$manager, $swap] = $this->mutual();
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('requestDecision', 89, 'approve');
        $swap->substituteShift->update(['user_id' => null]);
        $panel->call('confirmDecision')->assertHasErrors('decision')->assertSee('Análise desatualizada.')
            ->assertSee('Plantão de contrapartida #'.$swap->substitute_shift_id.': atribuição modificada.')
            ->assertSee('Atualizar análise')->call('openSwapAnalysis', 89)
            ->assertSet('pendingDecision', null)->assertSet('pendingDecisionSummary.can_approve', false)->assertHasNoErrors();
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_new_full_quota_is_revalidated_inside_confirmation_with_specific_reason(): void
    {
        [$manager, $swap] = $this->mutual();
        for ($i = 0; $i < 3; $i++) {
            $this->participation($swap->substitute);
        }
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('requestDecision', 89, 'approve');
        $this->participation($swap->substitute);
        $panel->call('confirmDecision')->assertHasErrors('decision')->assertSee('Análise desatualizada.')
            ->assertSee('Limite mensal atingido por Substituta QA: 4 de 4');
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertSame($swap->requester_id, $swap->shift->refresh()->user_id);
    }

    public function test_conflict_is_distinct_from_blocking_preconditions_and_keeps_the_existing_rule(): void
    {
        [$manager, $swap] = $this->mutual();
        $extra = $swap->shift->replicate();
        $extra->user_id = $swap->substitute_id;
        $extra->save();
        $analysis = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager);
        $this->assertTrue($analysis['can_approve']);
        $this->assertSame([], $analysis['approval_blockers']);
        $this->assertStringContainsString('Conflito de horários:', $analysis['warnings'][0]);
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', 89)
            ->assertSee('não bloqueiam automaticamente a decisão')->assertSee('Conflito de horários:');
    }

    public function test_diagnostics_preserve_manager_authorization(): void
    {
        [, $swap] = $this->mutual();
        $this->expectException(AuthorizationException::class);
        app(ShiftSwapAnalysis::class)->make($swap, $swap->requester);
    }
}
