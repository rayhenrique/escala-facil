<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\DeclineShiftSwapAction;
use App\Enums\ShiftSwapStatus;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use App\Support\OperationalAuditPresentation;
use App\Support\ShiftSwapReceipt;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class IncomingSwapResponseTest extends TestCase
{
    use RefreshDatabase;

    private function team(bool $mutual = false): array
    {
        $requester = User::factory()->create(['sector_id' => Sector::factory(), 'category_id' => Category::factory()]);
        $sub = User::factory()->create(['sector_id' => $requester->sector_id, 'category_id' => $requester->category_id]);
        $shift = $this->shift($requester, ['date' => today()->addDays(3), 'start_time' => '19:00', 'end_time' => '07:00']);
        $counterpart = $mutual ? $this->shift($sub, ['date' => today()->addDays(5), 'start_time' => '07:00', 'end_time' => '07:00', 'end_date' => today()->addDays(6)]) : null;
        $swap = ShiftSwap::factory()->create(['requester_id' => $requester->id, 'substitute_id' => $sub->id,
            'shift_id' => $shift->id, 'substitute_shift_id' => $counterpart?->id, 'status' => ShiftSwapStatus::PendingProfessional]);

        return [$requester, $sub, $swap, $shift, $counterpart];
    }

    private function shift(User $user, array $attributes): Shift
    {
        return Shift::factory()->create($attributes + ['user_id' => $user->id, 'sector_id' => $user->sector_id, 'category_id' => $user->category_id]);
    }

    public function test_cards_details_and_cancel_do_not_write_history(): void
    {
        [$requester, $sub, $swap] = $this->team(true);
        DB::enableQueryLog();
        Livewire::actingAs($sub)->test(MySchedule::class)->assertSee('Troca mútua')->assertSee('Ver detalhes')->assertSee('Recusar')
            ->call('openIncomingDetails', $swap->id)->assertSee('Detalhes do pedido recebido')->assertSee('dia seguinte')->assertSee('24h')
            ->assertSee($requester->name)->call('requestAcceptSwap', $swap->id)->assertSee('Nenhum plantão será transferido agora')
            ->call('cancelConfirmation')->assertSet('incomingDetailId', $swap->id)->call('closeIncomingDetails')->assertSet('incomingDetailId', null);
        $this->assertCount(0, collect(DB::getQueryLog())->filter(fn ($q) => preg_match('/^(insert|update|delete)/i', $q['query'])));
        DB::disableQueryLog();
    }

    public function test_mutual_acceptance_updates_queue_and_notifications_without_transferring_shifts(): void
    {
        [$requester, $sub, $swap, $shift, $counterpart] = $this->team(true);
        Livewire::actingAs($sub)->test(MySchedule::class)->assertViewHas('pendingResponses', 1)
            ->call('requestAcceptSwap', $swap->id)->call('confirmAcceptSwap')->assertHasNoErrors()
            ->assertViewHas('pendingResponses', 0)->assertDispatched('notifications-updated')->assertDispatched('incoming-response-finished')
            ->assertSet('incomingDetailId', null)->assertSet('swapReceipt.event', 'accepted');
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->fresh()->status);
        $this->assertSame($requester->id, $shift->fresh()->user_id);
        $this->assertSame($sub->id, $counterpart->fresh()->user_id);
        $this->assertSame('accepted', $swap->fresh()->professional_response);
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_decline_has_distinct_event_and_retains_original_receipt_and_assignments(): void
    {
        [$requester, $sub, $swap, $shift, $counterpart] = $this->team(true);
        app(ShiftSwapNotificationService::class)->requested($swap);
        $original = DB::table('notifications')->first();
        Livewire::actingAs($sub)->test(MySchedule::class)->call('requestDeclineSwap', $swap->id)
            ->set('declineReason', 'Não consigo cobrir neste dia.')->call('confirmDeclineSwap')->assertHasNoErrors()
            ->assertSet('swapReceipt.event', 'declined')->assertSee('Solicitação recusada pelo profissional')
            ->assertViewHas('pendingResponses', 0)->assertDispatched('notifications-updated');
        $this->assertSame(ShiftSwapStatus::Rejected, $swap->fresh()->status);
        $this->assertNull($swap->fresh()->manager_id);
        $this->assertNotNull($swap->fresh()->professional_responded_at);
        $this->assertSame($requester->id, $shift->fresh()->user_id);
        $this->assertSame($sub->id, $counterpart->fresh()->user_id);
        $this->assertSame($original->data, DB::table('notifications')->where('id', $original->id)->value('data'));
        $receipt = app(ShiftSwapReceipt::class)->current($swap->fresh());
        $this->assertSame('declined', $receipt['event']);
        $pdf = view('reports.swap-receipt', ['receipt' => $receipt, 'generatedAt' => now()->format('d/m/Y H:i')])->render();
        $this->assertStringContainsString('Solicitação recusada pelo profissional', $pdf);
        $this->assertStringContainsString('Motivo da recusa', $pdf);
        $this->assertStringNotContainsString('Justificativa da Gerência', $pdf);
        $this->assertDatabaseHas('operational_audit_events', ['entity_type' => 'swap', 'entity_id' => $swap->id, 'action' => 'declined', 'author_id' => $sub->id]);
    }

    public function test_decline_remains_available_with_incompatibility_or_full_quota_and_reason_is_optional(): void
    {
        [$requester, $sub, $swap] = $this->team();
        ShiftSwap::factory()->count(4)->create(['substitute_id' => $sub->id, 'status' => ShiftSwapStatus::Approved, 'created_at' => now()]);
        $sub->update(['sector_id' => Sector::factory()->create()->id]);
        Livewire::actingAs($sub)->test(MySchedule::class)->call('openIncomingDetails', $swap->id)->assertSee('incompatível')->assertSee('cota de 4 de 4')
            ->call('requestDeclineSwap', $swap->id)->call('confirmDeclineSwap')->assertHasNoErrors();
        $this->assertSame(4, $sub->approvedSwapCount(now()->month, now()->year));
    }

    public function test_overlap_and_adjacent_warnings_include_both_professionals(): void
    {
        [$requester, $sub, $swap, $shift, $counterpart] = $this->team(true);
        $this->shift($sub, ['date' => $shift->date->addDay(), 'start_time' => '00:00', 'end_time' => '08:00']);
        $this->shift($requester, ['date' => $counterpart->date->subDay(), 'start_time' => '19:00', 'end_time' => '07:00']);
        Livewire::actingAs($sub)->test(MySchedule::class)->call('requestAcceptSwap', $swap->id)
            ->assertSee('Conflito de horários')->assertSee('Dobra')->assertSee($requester->name)->assertSee($sub->name)
            ->call('confirmAcceptSwap')->assertHasNoErrors();
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->fresh()->status);
    }

    public function test_stale_counterpart_or_quota_requires_refresh_and_keeps_confirmation(): void
    {
        [$requester, $sub, $swap, $shift, $counterpart] = $this->team(true);
        $flow = Livewire::actingAs($sub)->test(MySchedule::class)->call('requestAcceptSwap', $swap->id);
        $counterpart->update(['user_id' => $requester->id]);
        $flow->call('confirmAcceptSwap')->assertHasErrors(['response'])->assertSee('atribuição do plantão de contrapartida')
            ->assertSet('pendingConfirmation', 'accept_swap')->call('refreshIncomingDetails')->assertSet('pendingConfirmation', null);
        $this->assertSame(ShiftSwapStatus::PendingProfessional, $swap->fresh()->status);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_recipient_only_can_view_and_respond_and_manager_policies_are_preserved(): void
    {
        [$requester, $sub, $swap] = $this->team();
        Livewire::actingAs($requester)->test(MySchedule::class)->call('openIncomingDetails', $swap->id)->assertNotFound();
        $manager = User::factory()->create(['role' => 'manager']);
        $this->assertFalse($manager->can('accept', $swap));
        $this->assertFalse($manager->can('decline', $swap));
        $this->assertFalse($sub->can('approve', $swap));
        $this->expectException(AuthorizationException::class);
        app(DeclineShiftSwapAction::class)->execute($requester, $swap->id);
    }

    public function test_two_stale_tabs_and_repeated_decisions_cannot_change_a_completed_response(): void
    {
        [$requester, $sub, $swap] = $this->team();
        $first = Livewire::actingAs($sub)->test(MySchedule::class)->call('requestAcceptSwap', $swap->id);
        $second = Livewire::actingAs($sub)->test(MySchedule::class)->call('requestDeclineSwap', $swap->id);
        $first->call('confirmAcceptSwap')->assertHasNoErrors();
        $second->call('confirmDeclineSwap')->assertHasErrors(['response'])->assertSee('já foi respondido')->call('refreshIncomingDetails')->assertSee('Aguardando gerência');
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->fresh()->status);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(1, OperationalAuditEvent::where('entity_type', 'swap')->where('entity_id', $swap->id)->whereIn('action', ['accepted', 'declined'])->count());
    }

    public function test_quota_uses_request_month_and_reason_validation_preserves_inputs(): void
    {
        [$requester, $sub, $swap] = $this->team();
        ShiftSwap::factory()->count(4)->create(['substitute_id' => $sub->id, 'status' => ShiftSwapStatus::Approved, 'created_at' => now()]);
        $swap->forceFill(['created_at' => now()->subMonth()])->save();
        $this->assertTrue($sub->can('accept', $swap->fresh()));
        $flow = Livewire::actingAs($sub)->test(MySchedule::class)->call('requestDeclineSwap', $swap->id)
            ->set('declineReason', str_repeat('a', 1001))->call('confirmDeclineSwap')->assertHasErrors(['declineReason'])->assertSet('pendingConfirmation', 'decline_swap');
        $this->assertSame(ShiftSwapStatus::PendingProfessional, $swap->fresh()->status);
        $flow->set('declineReason', '')->call('confirmDeclineSwap')->assertHasNoErrors();
    }

    public function test_stale_card_explains_current_state_instead_of_offering_a_duplicate_response(): void
    {
        [$requester, $sub, $swap] = $this->team();
        app(AcceptShiftSwapAction::class)->execute($sub, $swap->id);
        Livewire::actingAs($sub)->test(MySchedule::class)->call('requestDeclineSwap', $swap->id)
            ->assertSet('confirmationSummary.can_decline', false)->assertSee('já não aguarda sua resposta')
            ->call('confirmDeclineSwap')->assertHasErrors(['response']);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->fresh()->status);
        $this->assertSame('Recusada', OperationalAuditPresentation::value('professional_response', 'declined'));
    }
}
