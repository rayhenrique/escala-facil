<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\MonthlyCalendar;
use App\Livewire\NotificationsCenter;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use App\Support\ShiftSwapReceipt;
use App\Support\ShiftSwapReversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ShiftSwapHistoryImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_requested_event_is_immutable_after_accept(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);

        $requested = $substitute->notifications()->sole();
        $snapshot = $requested->data;
        $updatedAt = $requested->updated_at?->toIso8601String();

        $this->assertSame('requested', $snapshot['event']);
        $this->assertSame(ShiftSwapStatus::PendingProfessional->value, $snapshot['receipt']['status']);

        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        // O evento original não foi reescrito; novos eventos foram criados.
        $this->assertSame($snapshot, $requested->refresh()->data);
        $this->assertSame($updatedAt, $requested->refresh()->updated_at?->toIso8601String());

        $this->assertNotNull($requester->refresh()->notifications()->where('data->event', 'accepted')->first());
        $this->assertSame(ShiftSwapStatus::PendingManager, $shiftSwap->refresh()->status);
    }

    public function test_accepted_events_are_immutable_after_approval(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        $snapshots = $this->snapshotAll();
        $this->assertSame('Aguardando gerência', $manager->notifications()->sole()->data['receipt']['status_label']);

        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        // Nenhum comprovante anterior foi sobrescrito.
        $this->assertSnapshotsUnchanged($snapshots);

        // Novos eventos de aprovação existem apenas para os participantes.
        foreach ([$requester, $substitute] as $participant) {
            $approval = $participant->refresh()->notifications()->where('data->event', 'approved')->firstOrFail();
            $this->assertSame(ShiftSwapStatus::Approved->value, $approval->data['receipt']['status']);
            $this->assertSame('Aprovada', $approval->data['receipt']['status_label']);
        }

        // A gerência mantém o histórico de "aguardando"; o estado atual aparece em tela.
        $this->assertSame(1, $manager->refresh()->notifications()->count());
        $this->assertSame('accepted', $manager->refresh()->notifications()->sole()->data['event']);

        Livewire::actingAs($manager)
            ->test(NotificationsCenter::class)
            ->assertSee('Troca aguardando aprovação')
            ->assertSee('Situação atual da troca: Aprovada')
            ->assertSee('Este comprovante preserva o evento original.');

        $this->assertSnapshotsUnchanged($snapshots);
    }

    public function test_accepted_events_are_immutable_after_rejection(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        $snapshots = $this->snapshotAll();

        $justification = 'Cobertura mínima não atendida para o plantão.';
        app(DecideShiftSwapAction::class)->reject($manager, $shiftSwap->id, $justification);

        $this->assertSnapshotsUnchanged($snapshots);

        foreach ([$requester, $substitute] as $participant) {
            $rejection = $participant->refresh()->notifications()->where('data->event', 'rejected')->firstOrFail();
            $this->assertSame($justification, $rejection->data['receipt']['rejection_reason']);
        }

        $this->assertSame(ShiftSwapStatus::Rejected, $shiftSwap->refresh()->status);
        $this->assertSame($requester->id, $shift->refresh()->user_id);

        Livewire::actingAs($substitute)
            ->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Rejeitada')
            ->assertSee($justification);

        $this->assertSnapshotsUnchanged($snapshots);
    }

    public function test_approved_events_are_immutable_after_revert(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        $this->assertSame($substitute->id, $shift->refresh()->user_id);

        $snapshots = $this->snapshotAll();
        $approvedForRequester = $requester->refresh()->notifications()->where('data->event', 'approved')->firstOrFail()->data;

        app(RevertShiftSwapAction::class)->execute($manager, $shiftSwap->id, 'Correção administrativa.', app(ShiftSwapReversion::class)->inspect($shiftSwap->fresh())['fingerprint']);

        $this->assertSnapshotsUnchanged($snapshots);
        $this->assertSame($approvedForRequester, $requester->refresh()->notifications()->where('data->event', 'approved')->firstOrFail()->data);

        foreach ([$requester, $substitute] as $participant) {
            $this->assertNotNull(
                $participant->refresh()->notifications()->where('data->event', 'reverted')->first()
            );
        }

        $this->assertSame(ShiftSwapStatus::Reverted, $shiftSwap->refresh()->status);
        $this->assertSame($requester->id, $shift->refresh()->user_id);

        Livewire::actingAs($requester)
            ->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Desfeita')
            ->assertSee('Este comprovante preserva o evento original.');

        $this->assertSnapshotsUnchanged($snapshots);
    }

    public function test_notifications_center_render_does_not_write_to_database(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        $before = $this->snapshotAll();

        foreach ([$manager, $requester, $substitute] as $user) {
            Livewire::actingAs($user)->test(NotificationsCenter::class)->assertOk();
            Livewire::actingAs($user)->test(NotificationsCenter::class)->assertOk();
        }

        $this->assertSnapshotsUnchanged($before);
        $this->assertSame(ShiftSwapStatus::Approved, $shiftSwap->refresh()->status);
    }

    public function test_runtime_status_is_consistent_with_calendar_reports_and_pdf(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        $shiftSwap->refresh();

        // Fonte autoritativa: troca aprovada e plantão transferido.
        $this->assertSame(ShiftSwapStatus::Approved, $shiftSwap->status);
        $this->assertSame($substitute->id, $shift->refresh()->user_id);

        // Comprovante vivo (PDF/relatórios) reflete o estado atual.
        $liveReceipt = app(ShiftSwapReceipt::class)->make($shiftSwap, ShiftSwapEvent::Approved);
        $this->assertSame(ShiftSwapStatus::Approved->value, $liveReceipt['status']);
        $this->assertSame('Aprovada', $liveReceipt['status_label']);

        // Histórico preserva o evento original de solicitação.
        $requested = $substitute->notifications()->where('data->event', 'requested')->firstOrFail();
        $this->assertSame(ShiftSwapStatus::PendingProfessional->value, $requested->data['receipt']['status']);

        // Central exibe histórico + estado atual sem reescrever.
        Livewire::actingAs($substitute)
            ->test(NotificationsCenter::class)
            ->assertSee('Nova solicitação de troca')
            ->assertSee('Situação atual da troca: Aprovada');

        // Calendário reflete a transferência real.
        Livewire::actingAs($manager)
            ->test(MonthlyCalendar::class)
            ->set('month', $shift->date->format('Y-m'))
            ->call('selectDate', $shift->date->format('Y-m-d'))
            ->assertSee('Troca realizada')
            ->assertSee($requester->name)
            ->assertSee($substitute->name);

        // PDF oficial reflete o estado atual.
        $this->actingAs($manager)->get(route('swaps.receipt.pdf', $shiftSwap->id))->assertOk();
    }

    public function test_sync_command_preserves_existing_records(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        $before = $this->snapshotAll();

        $this->artisan('app:sync-swap-notifications')
            ->assertSuccessful()
            ->expectsOutputToContain('0 notificações sincronizadas');

        $this->assertSnapshotsUnchanged($before);
        $this->assertSame(ShiftSwapStatus::Approved, $shiftSwap->refresh()->status);
    }

    /** @return array<string, array{data: mixed, updated_at: ?string}> */
    private function snapshotAll(): array
    {
        $snapshots = [];

        foreach (DB::table('notifications')->orderBy('id')->get() as $row) {
            $snapshots[$row->id] = [
                'data' => json_decode((string) $row->data, true),
                'updated_at' => $row->updated_at,
            ];
        }

        return $snapshots;
    }

    /** @param array<string, array{data: mixed, updated_at: ?string}> $snapshots */
    private function assertSnapshotsUnchanged(array $snapshots): void
    {
        foreach ($snapshots as $id => $expected) {
            $row = DB::table('notifications')->where('id', $id)->first();
            $this->assertNotNull($row, "Notificação {$id} deveria continuar existindo.");
            $this->assertSame($expected['data'], json_decode((string) $row->data, true), "Comprovante {$id} deveria permanecer imutável.");
        }
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
            'date' => today()->addDays(10),
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
