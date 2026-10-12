<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\Dashboard;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapAnalysis;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardSwapAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_is_read_only_and_approval_updates_queue_receipt_and_notifications(): void
    {
        [$manager, $swap] = $this->pending();
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)
            ->set('period', '7')->call('openSwapAnalysis', $swap->id)
            ->assertSet('pendingDecision', null)->assertSee('Limite mensal')
            ->assertSee($swap->requester->name)->assertSee($swap->substitute->name);
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $panel->call('requestDecision', $swap->id, 'approve')->assertSee('Confirmar aprovação');
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $panel->call('confirmDecision')->assertHasNoErrors()->assertSet('pendingDecisionSwapId', null)
            ->assertSet('swapReceipt.event', 'approved')->assertSet('period', '7')
            ->assertDispatched('notifications-updated')->assertDontSeeHtml('data-attention="swap-'.$swap->id.'"');
        $this->assertSame($swap->substitute_id, $swap->shift->refresh()->user_id);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('approved', $swap->requester->notifications()->sole()->data['receipt']['event']);
    }

    public function test_rejection_requires_reason_and_keeps_assignments(): void
    {
        [$manager, $swap] = $this->pending();
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', $swap->id)
            ->call('requestDecision', $swap->id, 'reject')->call('confirmDecision')->assertHasErrors(['rejectionReason'])
            ->set('rejectionReason', 'Manter a cobertura original.')->call('confirmDecision')->assertHasNoErrors()
            ->assertSet('swapReceipt.rejection_reason', 'Manter a cobertura original.');
        $this->assertSame(ShiftSwapStatus::Rejected, $swap->refresh()->status);
        $this->assertSame($swap->requester_id, $swap->shift->refresh()->user_id);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_two_manager_snapshots_cannot_decide_the_same_request_twice(): void
    {
        [$manager, $swap] = $this->pending();
        $other = User::factory()->create(['role' => UserRole::Manager]);
        $one = Livewire::actingAs($manager)->test(Dashboard::class)->call('requestDecision', $swap->id, 'approve');
        $two = Livewire::actingAs($other)->test(Dashboard::class)->call('requestDecision', $swap->id, 'reject')
            ->set('rejectionReason', 'Manter escala.');
        $one->call('confirmDecision')->assertHasNoErrors();
        $two->call('confirmDecision')->assertHasErrors(['decision']);
        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 2);
        try {
            app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
            $this->fail('Repeated approval must be refused.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('notifications', 2);
        }
    }

    public function test_changed_schedule_and_new_conflict_require_another_analysis(): void
    {
        [$manager, $swap] = $this->pending();
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('requestDecision', $swap->id, 'approve');
        $extra = $swap->shift->replicate();
        $extra->user_id = $swap->substitute_id;
        $extra->save();
        $panel->call('confirmDecision')->assertHasErrors(['decision']);
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $panel->call('cancelDecision')->call('openSwapAnalysis', $swap->id)->assertSee('Conflito de horários');
    }

    public function test_changed_counterpart_is_not_transferred_without_ownership(): void
    {
        [$manager, $swap] = $this->pending(true);
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('requestDecision', $swap->id, 'approve');
        $swap->substituteShift->update(['user_id' => $swap->requester_id]);
        $panel->call('confirmDecision')->assertHasErrors(['decision']);
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->expectException(AuthorizationException::class);
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
    }

    public function test_professional_and_revoked_manager_cannot_decide(): void
    {
        [$manager, $swap] = $this->pending();
        Livewire::actingAs($swap->requester)->test(Dashboard::class)->assertForbidden();
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('requestDecision', $swap->id, 'approve');
        $manager->update(['role' => UserRole::Professional]);
        $panel->call('confirmDecision')->assertForbidden();
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_mutual_swap_ignores_released_assignments_and_warns_for_both_recipients(): void
    {
        [$manager, $swap] = $this->pending(true);
        $swap->substituteShift->update(['date' => $swap->shift->date, 'end_date' => $swap->shift->date]);
        $analysis = app(ShiftSwapAnalysis::class)->make($swap->fresh(), $manager);
        $this->assertSame([], $analysis['warnings']);
        foreach ([$swap->requester_id, $swap->substitute_id] as $id) {
            $adjacent = $swap->shift->replicate();
            $adjacent->fill(['user_id' => $id, 'start_time' => '19:00', 'end_time' => '07:00', 'end_date' => $swap->shift->date->addDay()])->save();
        }
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', $swap->id);
        $this->assertCount(2, $panel->get('pendingDecisionSummary')['warnings']);
        $panel->assertSee('Dobra:')->assertSee('Plantão de contrapartida')
            ->call('requestDecision', $swap->id, 'approve')->call('confirmDecision')->assertHasNoErrors();
        $this->assertSame($swap->requester_id, $swap->substituteShift->refresh()->user_id);
    }

    public function test_limit_warning_prevents_approval_but_allows_rejection(): void
    {
        [$manager, $swap] = $this->pending();
        for ($i = 0; $i < User::MONTHLY_SWAP_LIMIT; $i++) {
            ShiftSwap::factory()->create(['requester_id' => $swap->requester_id, 'substitute_id' => $swap->substitute_id,
                'status' => ShiftSwapStatus::Approved, 'created_at' => $swap->created_at]);
        }
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', $swap->id)
            ->assertSet('pendingDecisionSummary.can_approve', false)->assertSee('Limite mensal atingido')
            ->call('requestDecision', $swap->id, 'reject')->set('rejectionReason', 'Limite mensal atingido.')
            ->call('confirmDecision')->assertHasNoErrors();
    }

    public function test_notification_failure_rolls_back_status_and_both_assignments(): void
    {
        [$manager, $swap] = $this->pending(true);
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "notifications"')) {
                throw new \RuntimeException('Simulated notification storage failure');
            }
        });
        try {
            app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
            $this->fail('Notification failure must propagate.');
        } catch (\RuntimeException) {
            $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
            $this->assertSame($swap->requester_id, $swap->shift->refresh()->user_id);
            $this->assertSame($swap->substitute_id, $swap->substituteShift->refresh()->user_id);
            $this->assertDatabaseCount('notifications', 0);
        }
    }

    public function test_confirmation_cannot_be_called_without_a_reviewed_decision(): void
    {
        [$manager, $swap] = $this->pending();
        Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', $swap->id)
            ->call('confirmDecision')->assertNotFound();
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_rejection_reason_is_required_by_the_action_itself(): void
    {
        [$manager, $swap] = $this->pending();
        try {
            app(DecideShiftSwapAction::class)->reject($manager, $swap->id);
            $this->fail('Direct rejection without a reason must be refused.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('rejectionReason', $exception->errors());
            $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
            $this->assertDatabaseCount('notifications', 0);
        }
    }

    public function test_client_cannot_replace_the_reviewed_request_id(): void
    {
        [$manager, $swap] = $this->pending();
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)->call('openSwapAnalysis', $swap->id);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $panel->set('pendingDecisionSwapId', $swap->id + 1);
    }

    public function test_mobile_review_refresh_preserves_reason_and_requires_a_new_explicit_decision(): void
    {
        [$manager, $swap] = $this->pending(true);
        $panel = Livewire::actingAs($manager)->test(Dashboard::class)
            ->call('requestDecision', $swap->id, 'reject')
            ->set('rejectionReason', 'Manter cobertura original.')
            ->call('returnToSwapAnalysis')->call('refreshSwapAnalysis')
            ->assertSet('rejectionReason', 'Manter cobertura original.')
            ->assertSet('pendingDecision', null)
            ->assertSee(sprintf('TROCA-%06d', $swap->id))
            ->assertSee('Aguardando gerência');
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);
        $this->assertDatabaseCount('notifications', 0);
        $panel->call('requestDecision', $swap->id, 'reject')->call('confirmDecision')
            ->assertHasNoErrors()->assertSet('pendingDecisionSwapId', null);
        $this->assertSame($swap->requester_id, $swap->shift->refresh()->user_id);
        $this->assertSame($swap->substitute_id, $swap->substituteShift->refresh()->user_id);
    }

    public function test_confirmation_presents_both_transfers_before_any_mutation(): void
    {
        [$manager, $swap] = $this->pending(true);
        Livewire::actingAs($manager)->test(Dashboard::class)
            ->call('requestDecision', $swap->id, 'approve')
            ->assertSee('Quem assume cada plantão')
            ->assertSee($swap->substitute->name.' assume')
            ->assertSee($swap->requester->name.' assume')
            ->assertSee('Conferir atribuições e limite mensal');
        $this->assertSame($swap->requester_id, $swap->shift->refresh()->user_id);
        $this->assertSame($swap->substitute_id, $swap->substituteShift->refresh()->user_id);
        $this->assertDatabaseCount('notifications', 0);
    }

    private function pending(bool $mutual = false): array
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
        $requester = User::factory()->create(['category_id' => $category->id, 'sector_id' => $sector->id]);
        $substitute = User::factory()->create(['category_id' => $category->id, 'sector_id' => $sector->id]);
        $shift = Shift::factory()->create(['date' => today()->addDay(), 'start_time' => '07:00', 'end_time' => '19:00',
            'end_date' => today()->addDay(), 'category_id' => $category->id, 'sector_id' => $sector->id,
            'user_id' => $requester->id, 'status' => ShiftStatus::Scheduled]);
        $counterpart = null;
        if ($mutual) {
            $counterpart = $shift->replicate();
            $counterpart->fill(['user_id' => $substitute->id, 'date' => today()->addDays(2), 'end_date' => today()->addDays(2)])->save();
        }
        $swap = ShiftSwap::factory()->create(['shift_id' => $shift->id, 'substitute_shift_id' => $counterpart?->id,
            'requester_id' => $requester->id, 'substitute_id' => $substitute->id, 'status' => ShiftSwapStatus::PendingManager]);

        return [$manager, $swap];
    }
}
