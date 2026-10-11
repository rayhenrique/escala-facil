<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\MonthlyCalendar;
use App\Livewire\NotificationNavItem;
use App\Livewire\NotificationsCenter;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Support\ShiftSwapReceipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftSwapEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pwa_badge_and_notification_triggers_are_exposed(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        Livewire::actingAs($requester)
            ->test(NotificationNavItem::class)
            ->assertSeeHtml('data-unread-count="0"')
            ->assertSeeHtml('window.syncAppBadge(0)');

        // Now create a swap so substitute gets an unread notification
        app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);

        Livewire::actingAs($substitute)
            ->test(NotificationNavItem::class)
            ->assertSeeHtml('data-unread-count="1"')
            ->assertSeeHtml('window.syncAppBadge(1)');

        Livewire::actingAs($substitute)
            ->test(NotificationsCenter::class)
            ->assertSee('Ativar avisos no aparelho');
    }

    public function test_manager_sees_both_shifts_overview_and_approves_mutual_swap(): void
    {
        [$manager, $requester, $substitute, $shiftA, $category, $sector] = $this->teamWithExtras();

        // Create shiftB for substitute
        $shiftB = Shift::query()->create([
            'date' => today()->addDays(6),
            'start_time' => '07:00:00',
            'end_time' => '19:00:00',
            'category_id' => $category->id,
            'sector_id' => $sector->id,
            'user_id' => $substitute->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        // Requester creates swap selecting substitute and counterpart shiftB
        Livewire::actingAs($requester)
            ->test(MySchedule::class)
            ->call('openSwap', $shiftA->id)
            ->set('substituteId', (string) $substitute->id)
            ->set('substituteShiftId', (string) $shiftB->id)
            ->call('reviewSwapRequest')
            ->assertSet('pendingConfirmation', 'request_swap')
            ->assertSee($substitute->name)
            ->call('confirmSwapRequest')
            ->assertHasNoErrors();

        $swap = ShiftSwap::query()->sole();
        $this->assertSame($shiftA->id, $swap->shift_id);
        $this->assertSame($shiftB->id, $swap->substitute_shift_id);
        $this->assertSame(ShiftSwapStatus::PendingProfessional, $swap->status);

        // Substitute accepts
        Livewire::actingAs($substitute)
            ->test(MySchedule::class)
            ->assertSee('Contrapartida:')
            ->call('requestAcceptSwap', $swap->id)
            ->assertSet('pendingConfirmation', 'accept_swap')
            ->call('confirmAcceptSwap')
            ->assertHasNoErrors();

        $this->assertSame(ShiftSwapStatus::PendingManager, $swap->refresh()->status);

        // Manager reviews swap approvals
        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->assertSee('Troca mútua')
            ->assertSee('Plantão de contrapartida')
            ->assertSee($requester->name)
            ->assertSee($substitute->name)
            ->call('requestDecision', $swap->id, 'approve')
            ->assertSet('pendingDecision', 'approve')
            ->assertSee('Troca geral:')
            ->call('confirmDecision')
            ->assertHasNoErrors();

        $this->assertSame(ShiftSwapStatus::Approved, $swap->refresh()->status);

        // Shifts transferred mutually:
        // Shift A now belongs to substitute, Shift B now belongs to requester!
        $this->assertSame($substitute->id, $shiftA->refresh()->user_id);
        $this->assertSame($requester->id, $shiftB->refresh()->user_id);

        // Receipts show detailed movements
        $receipt = app(ShiftSwapReceipt::class)->make($swap, ShiftSwapEvent::Approved);
        $this->assertTrue($receipt['has_counterpart']);
        $this->assertStringContainsString($requester->name, $receipt['requester_movement']);
        $this->assertStringContainsString($substitute->name, $receipt['substitute_movement']);

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', $shiftA->date->format('Y-m'))
            ->call('selectDate', $shiftA->date->format('Y-m-d'))
            ->assertSee('Troca realizada')
            ->assertSee('Mútua')
            ->assertSee('Troca mútua realizada entre '.$requester->name.' e '.$substitute->name);
    }

    public function test_manager_rejection_requires_justification_and_notifies_both_with_comprovante(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $swap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);

        // Manager tries to reject without justification
        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->call('requestDecision', $swap->id, 'reject')
            ->set('rejectionReason', '')
            ->call('confirmDecision')
            ->assertHasErrors(['rejectionReason']);

        // Manager provides justification
        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->call('requestDecision', $swap->id, 'reject')
            ->set('rejectionReason', 'Setor precisa manter a equipe original para auditoria do plantão.')
            ->call('confirmDecision')
            ->assertHasNoErrors();

        $swap->refresh();
        $this->assertSame(ShiftSwapStatus::Rejected, $swap->status);
        $this->assertSame('Setor precisa manter a equipe original para auditoria do plantão.', $swap->rejection_reason);

        // Notification delivered to requester and substitute with justification
        $requesterNotification = $requester->notifications()->where('data->event', 'rejected')->sole();
        $substituteNotification = $substitute->notifications()->where('data->event', 'rejected')->sole();

        $this->assertStringContainsString('Setor precisa manter a equipe original para auditoria do plantão.', $requesterNotification->data['message']);
        $this->assertStringContainsString('Setor precisa manter a equipe original para auditoria do plantão.', $substituteNotification->data['message']);
        $this->assertSame('Setor precisa manter a equipe original para auditoria do plantão.', $requesterNotification->data['receipt']['rejection_reason']);
    }

    public function test_calendar_highlights_days_and_shifts_with_approved_swaps(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $swap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);

        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', $shift->date->format('Y-m'))
            ->call('selectDate', $shift->date->format('Y-m-d'))
            ->assertSee('Troca realizada')
            ->assertSee('⇄')
            ->assertSeeHtml('daily-shift-card-swapped')
            ->assertSee($requester->name)
            ->assertSee($substitute->name)
            ->assertSeeHtml('title="'.e('Troca realizada: '.$requester->name.' trocou com '.$substitute->name.' (Solicitante: '.$requester->name.' → Substituto: '.$substitute->name.')').'"');

        Livewire::actingAs($substitute)
            ->test(MySchedule::class)
            ->set('month', $shift->date->format('Y-m'))
            ->assertSee('Troca realizada')
            ->assertSee($requester->name)
            ->assertSee($substitute->name);
    }

    public function test_swap_receipt_pdf_download_and_authorization(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $swap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);

        $url = route('swaps.receipt.pdf', $swap->id);

        // Guest redirected to login
        $this->get($url)->assertRedirect(route('login'));

        // Unauthorized professional receives 403 Forbidden
        $otherUser = User::factory()->create([
            'role' => UserRole::Professional,
        ]);
        $this->actingAs($otherUser)->get($url)->assertForbidden();

        // Requester can download PDF
        $response = $this->actingAs($requester)->get($url);
        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('comprovante-TROCA-', (string) $response->headers->get('content-disposition'));

        // Substitute can download PDF
        $this->actingAs($substitute)->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');

        // Manager can download PDF
        $this->actingAs($manager)->get($url)->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Shift}
     */
    private function team(): array
    {
        [$manager, $requester, $substitute, $shift] = $this->teamWithExtras();

        return [$manager, $requester, $substitute, $shift];
    }

    /**
     * @return array{0: User, 1: User, 2: User, 3: Shift, 4: Category, 5: Sector}
     */
    private function teamWithExtras(): array
    {
        $category = Category::query()->create(['name' => 'Médico Clínico']);
        $sector = Sector::query()->create(['name' => 'Emergência']);

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        $requester = User::factory()->create([
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        $substitute = User::factory()->create([
            'role' => UserRole::Professional,
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);

        $shift = Shift::query()->create([
            'date' => today()->addDays(3),
            'start_time' => '07:00:00',
            'end_time' => '19:00:00',
            'category_id' => $category->id,
            'sector_id' => $sector->id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        return [$manager, $requester, $substitute, $shift, $category, $sector];
    }
}
