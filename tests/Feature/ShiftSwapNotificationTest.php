<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\Manager\SwapApprovals;
use App\Livewire\NotificationsCenter;
use App\Livewire\Professional\MySchedule;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Services\ShiftSwapNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class ShiftSwapNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_acceptance_and_approval_notify_every_participant_with_receipts(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();
        $secondManager = User::factory()->create(['role' => UserRole::Manager]);

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);

        $requestNotification = $substitute->notifications()->sole();
        $this->assertSame('requested', $requestNotification->data['event']);
        $this->assertSame('Nova solicitação de troca', $requestNotification->data['title']);
        $this->assertSame('TROCA-'.str_pad((string) $shiftSwap->id, 6, '0', STR_PAD_LEFT), $requestNotification->data['receipt']['reference']);
        $this->assertSame($shift->date->format('Y-m-d'), $requestNotification->data['receipt']['shift_date']);
        $this->assertSame('Emergência', $requestNotification->data['receipt']['sector']);
        $this->assertSame('Médico Clínico', $requestNotification->data['receipt']['category']);
        $this->assertSame($requester->name, $requestNotification->data['receipt']['requester']);
        $this->assertSame($substitute->name, $requestNotification->data['receipt']['substitute']);

        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        $this->assertSame('accepted', $requester->notifications()->sole()->data['event']);
        $this->assertCount(2, $substitute->notifications);
        $this->assertNotNull($substitute->notifications->first(
            fn ($notification): bool => $notification->data['event'] === 'accepted',
        ));

        foreach ([$manager, $secondManager] as $recipient) {
            $managerNotification = $recipient->notifications()->sole();
            $this->assertSame('accepted', $managerNotification->data['event']);
            $this->assertSame('Troca aguardando aprovação', $managerNotification->data['title']);
            $this->assertSame('/manager/swaps', $managerNotification->data['target_url']);
        }

        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        $approvalForRequester = $requester->notifications()->get()->first(
            fn ($notification): bool => $notification->data['event'] === 'approved',
        );
        $approvalForSubstitute = $substitute->notifications()->get()->first(
            fn ($notification): bool => $notification->data['event'] === 'approved',
        );

        $this->assertSame('approved', $approvalForRequester->data['event']);
        $this->assertSame('approved', $approvalForSubstitute->data['event']);
        $this->assertSame($manager->name, $approvalForRequester->data['receipt']['manager']);
        $this->assertSame('Aprovada', $approvalForRequester->data['receipt']['status_label']);
        $this->assertSame($substitute->id, $shift->refresh()->user_id);
    }

    public function test_each_actor_sees_an_immediate_receipt_after_changing_the_swap(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        Livewire::actingAs($requester)
            ->test(MySchedule::class)
            ->call('openSwap', $shift->id)
            ->set('substituteId', (string) $substitute->id)
            ->call('requestSwap')
            ->assertSet('swapReceipt.event', 'requested')
            ->assertSee('Comprovante da troca')
            ->assertSee('Solicitação enviada');

        $shiftSwap = ShiftSwap::query()->sole();

        Livewire::actingAs($substitute)
            ->test(MySchedule::class)
            ->call('acceptSwap', $shiftSwap->id)
            ->assertSet('swapReceipt.event', 'accepted')
            ->assertSee('Solicitação aceita');

        Livewire::actingAs($manager)
            ->test(SwapApprovals::class)
            ->call('approve', $shiftSwap->id)
            ->assertSet('swapReceipt.event', 'approved')
            ->assertSee('Troca aprovada')
            ->assertSee($manager->name);
    }

    public function test_rejection_notifies_both_professionals_and_keeps_the_original_schedule(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();
        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        app(DecideShiftSwapAction::class)->reject($manager, $shiftSwap->id, 'Manter a cobertura original.');

        $this->assertNotNull($requester->notifications->first(
            fn ($notification): bool => $notification->data['event'] === 'rejected',
        ));
        $this->assertNotNull($substitute->notifications->first(
            fn ($notification): bool => $notification->data['event'] === 'rejected',
        ));
        $this->assertSame($requester->id, $shift->refresh()->user_id);
    }

    public function test_notification_center_is_private_and_allows_read_management(): void
    {
        [, $requester, $substitute, $shift] = $this->team();
        app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);

        $notification = $substitute->notifications()->sole();

        Livewire::actingAs($requester)
            ->test(NotificationsCenter::class)
            ->assertDontSee($notification->data['title']);

        Livewire::actingAs($substitute)
            ->test(NotificationsCenter::class)
            ->assertSee('Nova solicitação de troca')
            ->assertSee($notification->data['receipt']['reference'])
            ->assertSet('paginators.page', 1)
            ->call('markAsRead', $notification->id);

        $this->assertNotNull($notification->refresh()->read_at);

        $secondShift = Shift::query()->create([
            'date' => today()->addDays(8),
            'start_time' => '19:00',
            'end_time' => '07:00',
            'sector_id' => $shift->sector_id,
            'category_id' => $shift->category_id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);
        app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $secondShift->id,
            'substitute_id' => $substitute->id,
        ]);

        Livewire::actingAs($substitute)
            ->test(NotificationsCenter::class)
            ->assertSee('notificação não lida')
            ->call('requestMarkAllAsRead')
            ->assertSet('showMarkAllConfirmation', true)
            ->assertSee('Marcar todas como lidas?')
            ->call('cancelMarkAllAsRead')
            ->assertSet('showMarkAllConfirmation', false)
            ->call('requestMarkAllAsRead')
            ->call('markAllAsRead')
            ->assertSee('Todas as notificações foram marcadas como lidas.');

        $this->assertSame(0, $substitute->unreadNotifications()->count());
    }

    public function test_notifications_are_removed_with_a_deletable_user(): void
    {
        [, $requester, $substitute, $shift] = $this->team();
        app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);

        $notificationId = $substitute->notifications()->sole()->id;
        $shift->delete();
        $substitute->delete();

        $this->assertDatabaseMissing('notifications', ['id' => $notificationId]);
    }

    public function test_approval_is_rolled_back_when_its_notification_cannot_be_persisted(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();
        $shiftSwap = ShiftSwap::query()->create([
            'shift_id' => $shift->id,
            'requester_id' => $requester->id,
            'substitute_id' => $substitute->id,
            'status' => ShiftSwapStatus::PendingManager,
        ]);

        $this->mock(ShiftSwapNotificationService::class)
            ->shouldReceive('approved')
            ->once()
            ->andThrow(new RuntimeException('Falha simulada ao persistir a notificação.'));

        $this->assertThrows(
            fn () => app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id),
            RuntimeException::class,
        );

        $this->assertSame(ShiftSwapStatus::PendingManager, $shiftSwap->refresh()->status);
        $this->assertNull($shiftSwap->manager_id);
        $this->assertSame($requester->id, $shift->refresh()->user_id);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_manager_rejection_preserves_history_and_shows_current_status_at_runtime(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        $managerNotification = $manager->notifications()->sole();
        $this->assertSame('Troca aguardando aprovação', $managerNotification->data['title']);
        $this->assertSame('Aguardando gerência', $managerNotification->data['receipt']['status_label']);
        $snapshotBeforeDecision = $managerNotification->data;

        // Manager rejects with justification
        $justification = 'Colocar o horário do turno correto. Está ficando 20/10 MT por 20/10 mt';
        app(DecideShiftSwapAction::class)->reject($manager, $shiftSwap->id, $justification);

        // Histórico da gerência permanece imutável; participantes recebem o evento de rejeição.
        $preservedNotification = $manager->refresh()->notifications()->sole();
        $this->assertSame('accepted', $preservedNotification->data['event']);
        $this->assertSame('Troca aguardando aprovação', $preservedNotification->data['title']);
        $this->assertSame('Aguardando gerência', $preservedNotification->data['receipt']['status_label']);
        $this->assertSame($snapshotBeforeDecision, $preservedNotification->data);

        foreach ([$requester, $substitute] as $participant) {
            $rejection = $participant->refresh()->notifications()
                ->where('data->event', 'rejected')
                ->firstOrFail();
            $this->assertSame($justification, $rejection->data['receipt']['rejection_reason']);
            $this->assertStringContainsString($justification, $rejection->data['message']);
        }

        // A Central não reescreve o banco e exibe o estado atual em tempo de execução.
        $dataBeforeRender = $preservedNotification->refresh()->data;
        $updatedAtBeforeRender = $preservedNotification->refresh()->updated_at?->toIso8601String();

        Livewire::actingAs($manager)
            ->test(NotificationsCenter::class)
            ->assertSee('Troca aguardando aprovação')
            ->assertSee('Situação atual da troca: Rejeitada')
            ->assertSee('Rejeitada')
            ->assertSee($justification)
            ->assertSee($manager->name)
            ->assertSee('Este comprovante preserva o evento original.');

        $this->assertSame($dataBeforeRender, $preservedNotification->refresh()->data);
        $this->assertSame($updatedAtBeforeRender, $preservedNotification->refresh()->updated_at?->toIso8601String());

        // PDF reflete o estado atual, enquanto o histórico preserva o evento original.
        $response = $this->actingAs($manager)->get(route('swaps.receipt.pdf', $shiftSwap->id));
        $response->assertOk();
    }

    /** @return array{User, User, User, Shift} */
    private function team(): array
    {
        $category = Category::query()->create(['name' => 'Médico Clínico']);
        $sector = Sector::query()->create(['name' => 'Emergência']);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $requester = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $substitute = User::factory()->create([
            'category_id' => $category->id,
            'sector_id' => $sector->id,
        ]);
        $shift = Shift::query()->create([
            'date' => today()->addDays(5),
            'start_time' => '07:00',
            'end_time' => '19:00',
            'sector_id' => $sector->id,
            'category_id' => $category->id,
            'user_id' => $requester->id,
            'status' => ShiftStatus::Scheduled,
        ]);

        return [$manager, $requester, $substitute, $shift];
    }
}
