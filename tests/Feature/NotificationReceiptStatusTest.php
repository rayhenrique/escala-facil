<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Actions\ShiftSwaps\RevertShiftSwapAction;
use App\Enums\ShiftSwapEvent;
use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\NotificationsCenter;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\ShiftSwapEventNotification;
use App\Services\ShiftSwapNotificationService;
use App\Support\ShiftSwapReceipt;
use App\Support\ShiftSwapReversion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class NotificationReceiptStatusTest extends TestCase
{
    use RefreshDatabase;

    private function pending(int $id = 106): array
    {
        $category = Category::factory()->create();
        $sector = Sector::factory()->create();
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $data = ['role' => UserRole::Professional, 'category_id' => $category->id, 'sector_id' => $sector->id];
        $requester = User::factory()->create($data);
        $substitute = User::factory()->create($data);
        $shift = Shift::factory()->create(['date' => today()->addDays(10), 'start_time' => '07:00', 'end_time' => '19:00',
            'status' => 'scheduled', 'user_id' => $requester->id, 'sector_id' => $sector->id, 'category_id' => $category->id]);
        $swap = ShiftSwap::factory()->create(['id' => $id, 'shift_id' => $shift->id, 'requester_id' => $requester->id,
            'substitute_id' => $substitute->id, 'status' => ShiftSwapStatus::PendingProfessional]);
        app(ShiftSwapNotificationService::class)->requested($swap);

        return [$manager, $requester, $substitute, $swap];
    }

    public function test_106_approved_state_is_primary_and_accepted_receipt_remains_historical_without_writes(): void
    {
        [$manager, , $substitute, $swap] = $this->pending();
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        $notification = $manager->notifications()->sole();
        $original = $notification->getAttributes();
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        $before = DB::table('notifications')->orderBy('id')->get()->toArray();
        DB::enableQueryLog();
        try {
            $screen = Livewire::actingAs($manager)->test(NotificationsCenter::class)
                ->assertSee('Situação atual da troca: Aprovada')->assertSee('Evento histórico desta notificação')
                ->assertSee('Troca aguardando aprovação')->assertSee('Comprovante histórico da notificação')
                ->assertSee('Situação no momento deste registro')->assertSee('Aguardando gerência')
                ->assertSee('TROCA-000106')->assertSee('PDF oficial atualizado')->assertSee('Imprimir registro histórico');
            $html = $screen->html();
            $this->assertLessThan(strpos($html, 'Troca aguardando aprovação'), strpos($html, 'Situação atual da troca: Aprovada'));
            $this->assertStringNotContainsString('status-pending_manager', $html);
            $this->assertSame([], collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => preg_match('/^\s*(insert|update|delete|replace|alter|create|drop)\b/i', $sql))->values()->all());
        } finally {
            DB::disableQueryLog();
        }
        $this->assertEquals($original, $notification->fresh()->getAttributes());
        $this->assertEquals($before, DB::table('notifications')->orderBy('id')->get()->toArray());
        $this->assertSame(ShiftSwapStatus::Approved, $swap->fresh()->status);
        $this->assertSame('pending_manager', $notification->fresh()->data['receipt']['status']);
        $this->assertCurrentPdf($manager, $swap, ShiftSwapStatus::Approved);
    }

    public function test_requested_accepted_approved_rejected_and_reverted_labels_remain_coherent(): void
    {
        [$manager, $requester, $substitute, $swap] = $this->pending();
        Livewire::actingAs($substitute)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Aguardando profissional')->assertSee('Solicitação enviada');
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        Livewire::actingAs($manager)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Aguardando gerência')->assertSee('Solicitação aceita');
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        Livewire::actingAs($requester)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Aprovada')->assertSee('Troca aprovada')->assertSee('Solicitação aceita');
        $original = DB::table('notifications')->orderBy('id')->get();
        app(RevertShiftSwapAction::class)->execute($manager, $swap->id, 'Restaurar a atribuição original.', app(ShiftSwapReversion::class)->inspect($swap->fresh())['fingerprint']);
        Livewire::actingAs($substitute)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Desfeita')->assertSee('Troca desfeita')->assertSee('Troca aprovada')
            ->assertSee('Solicitação aceita')->assertSee('Solicitação enviada')->assertSee('Situação no momento deste registro');
        foreach ($original as $row) {
            $this->assertEquals($row, DB::table('notifications')->find($row->id));
        }
        $this->assertCurrentPdf($manager, $swap, ShiftSwapStatus::Reverted);

        [$otherManager, , $otherSubstitute, $rejected] = $this->pending(107);
        app(AcceptShiftSwapAction::class)->execute($otherSubstitute, $rejected->id);
        app(DecideShiftSwapAction::class)->reject($otherManager, $rejected->id, 'Manter a escala original.');
        Livewire::actingAs($otherSubstitute)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Rejeitada')->assertSee('Troca rejeitada')->assertSee('Manter a escala original.');
        $this->assertCurrentPdf($otherManager, $rejected, ShiftSwapStatus::Rejected);
    }

    public function test_current_states_are_loaded_once_per_page_without_n_plus_one(): void
    {
        [$manager, , , $swap] = $this->pending();
        // Ten distinct swaps and managers prove the query count is not tied to record count.
        foreach (range(1, 12) as $id) {
            $other = ShiftSwap::factory()->approved()->create(['manager_id' => User::factory()->create(['role' => UserRole::Manager])->id]);
            $manager->notify(new ShiftSwapEventNotification(['title' => 'Troca aprovada', 'message' => 'Registro histórico.', 'target_url' => '/notifications',
                'receipt' => app(ShiftSwapReceipt::class)->make($other, ShiftSwapEvent::Approved)]));
        }
        DB::enableQueryLog();
        try {
            $screen = Livewire::actingAs($manager)->test(NotificationsCenter::class)
                ->assertViewHas('notifications', fn ($rows) => $rows->count() === 10 && $rows->total() === 12)
                ->assertViewHas('swapStatuses', fn ($states) => count($states) === 10);
            $queries = collect(DB::getQueryLog())->pluck('query');
            $this->assertSame(1, $queries->filter(fn ($sql) => str_contains($sql, 'from "shift_swaps"'))->count());
            $this->assertSame(1, $queries->filter(fn ($sql) => str_contains($sql, 'from "users"') && str_contains($sql, '"users"."id" in'))->count());
            DB::flushQueryLog();
            $screen->call('gotoPage', 2)->assertViewHas('notifications', fn ($rows) => $rows->count() === 2)
                ->assertViewHas('swapStatuses', fn ($states) => count($states) === 2);
            $this->assertSame(1, collect(DB::getQueryLog())->pluck('query')->filter(fn ($sql) => str_contains($sql, 'from "shift_swaps"'))->count());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_legacy_reference_is_resolved_without_changing_saved_payload(): void
    {
        [$manager, , $substitute, $swap] = $this->pending();
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        app(DecideShiftSwapAction::class)->approve($manager, $swap->id);
        $receipt = app(ShiftSwapReceipt::class)->make($swap->fresh(), ShiftSwapEvent::Accepted);
        unset($receipt['shift_swap_id']);
        $receipt['status'] = 'pending_manager';
        $receipt['status_label'] = 'Aguardando gerência';
        $manager->notify(new ShiftSwapEventNotification(['title' => 'Solicitação aceita', 'message' => 'Evento antigo.', 'target_url' => '/notifications', 'receipt' => $receipt]));
        $before = DB::table('notifications')->orderBy('id')->get();
        Livewire::actingAs($manager)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Aprovada')->assertSee('Situação no momento deste registro');
        $this->assertEquals($before, DB::table('notifications')->orderBy('id')->get());
    }

    public function test_missing_or_unauthorized_current_record_never_uses_old_status_as_current(): void
    {
        [$manager, , $substitute, $swap] = $this->pending();
        $outsider = User::factory()->create();
        $receipt = app(ShiftSwapReceipt::class)->make($swap, ShiftSwapEvent::Requested);
        $outsider->notify(new ShiftSwapEventNotification(['title' => 'Evento antigo', 'message' => 'Histórico preservado.', 'target_url' => '/notifications', 'receipt' => $receipt]));
        Livewire::actingAs($outsider)->test(NotificationsCenter::class)
            ->assertSee('Situação atual da troca: Indisponível para consulta')->assertDontSeeHtml('href="'.route('swaps.receipt.pdf', $swap).'"')
            ->assertViewHas('swapStatuses', fn ($states) => $states === []);
        $this->actingAs($outsider)->get(route('swaps.receipt.pdf', $swap))->assertForbidden();
        $this->actingAs($outsider)->call('GET', route('notifications'))->assertOk();
        $this->actingAs($substitute)->get(route('swaps.receipt.pdf', $swap))->assertOk();
        $receipt['shift_swap_id'] = 999999;
        $receipt['reference'] = 'TROCA-999999';
        $manager->notify(new ShiftSwapEventNotification(['title' => 'Evento antigo', 'message' => 'Histórico preservado.', 'target_url' => '/notifications', 'receipt' => $receipt]));
        Livewire::actingAs($manager)->test(NotificationsCenter::class)->assertSee('Situação atual da troca: Indisponível para consulta');
    }

    public function test_only_explicit_read_actions_write_and_remain_scoped_to_the_recipient(): void
    {
        [$manager, , $substitute, $swap] = $this->pending();
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        $own = $manager->notifications()->sole();
        $other = $substitute->notifications()->firstOrFail();
        Livewire::actingAs($manager)->test(NotificationsCenter::class)->call('markAsRead', $other->id)->assertNotFound();
        $this->assertNull($other->fresh()->read_at);
        $before = $own->data;
        Livewire::actingAs($manager)->test(NotificationsCenter::class)->call('markAsRead', $own->id);
        $this->assertNotNull($own->fresh()->read_at);
        $this->assertSame($before, $own->fresh()->data);
    }

    public function test_receipt_reference_parsing_is_strict_and_does_not_invent_an_id(): void
    {
        $this->assertSame(106, ShiftSwapReceipt::swapId(['reference' => 'TROCA-000106']));
        $this->assertSame(106, ShiftSwapReceipt::swapId(['shift_swap_id' => '106']));
        foreach ([null, [], ['reference' => 'qualquer-106'], ['reference' => 'TROCA-000106-extra'], ['shift_swap_id' => '1e2'], ['shift_swap_id' => -106], ['shift_swap_id' => 106.5], ['reference' => ['106']]] as $invalid) {
            $this->assertNull(ShiftSwapReceipt::swapId($invalid));
        }
    }

    public function test_compact_overview_preserves_night_and_24_hour_counterpart_without_changing_snapshot(): void
    {
        $receipt = ['requester' => 'Maria', 'substitute' => 'Ana', 'has_counterpart' => true,
            'shift_date' => '2026-10-31', 'start_time' => '19:00', 'end_time' => '07:00', 'sector' => 'UTI',
            'counterpart_shift_date' => '2026-11-02', 'counterpart_start_time' => '07:00', 'counterpart_end_time' => '07:00',
            'counterpart_end_date' => '2026-11-03', 'counterpart_sector' => 'Clínica'];
        $original = $receipt;
        $summary = ShiftSwapReceipt::compactSummary($receipt);
        $this->assertCount(3, $summary);
        $this->assertSame('Solicitante: Maria · Substituto: Ana', $summary[0]);
        $this->assertStringContainsString('dia seguinte, 01/11/2026', $summary[1]);
        $this->assertStringContainsString('12h · UTI', $summary[1]);
        $this->assertStringContainsString('dia seguinte, 03/11/2026', $summary[2]);
        $this->assertStringContainsString('24h · Clínica', $summary[2]);
        $this->assertSame($original, $receipt);
    }

    public function test_compact_notifications_have_distinct_accessible_detail_panels_and_bulk_read_preserves_history(): void
    {
        [$manager, , $substitute, $swap] = $this->pending();
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        $other = User::factory()->create();
        $payload = $manager->notifications()->sole()->data;
        $manager->notify(new ShiftSwapEventNotification($payload));
        $other->notify(new ShiftSwapEventNotification($payload));
        $before = $manager->notifications()->get()->keyBy('id');
        $screen = Livewire::actingAs($manager)->test(NotificationsCenter::class);
        foreach ($before as $row) {
            $screen->assertSeeHtml('aria-controls="notification-details-'.$row->id.'"')
                ->assertSeeHtml('id="notification-details-'.$row->id.'"')
                ->assertSeeHtml('aria-labelledby="notification-toggle-'.$row->id.'"');
            $this->assertEquals($row->getAttributes(), $row->fresh()->getAttributes());
        }
        $screen->assertSee('Ver detalhes')->assertSee('Marcar como lida')->assertSee('Abrir módulo')
            ->assertSee('PDF oficial atualizado')->assertSee('Comprovante histórico da notificação')
            ->assertSeeHtml('aria-expanded="false"')->assertSeeHtml('x-cloak');
        $screen->call('requestMarkAllAsRead')->assertSet('showMarkAllConfirmation', true);
        $this->assertSame(2, $manager->unreadNotifications()->count());
        $screen->call('cancelMarkAllAsRead')->assertSet('showMarkAllConfirmation', false);
        $screen->call('markAllAsRead');
        $this->assertSame(0, $manager->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
        foreach ($before as $row) {
            $this->assertSame($row->data, $row->fresh()->data);
        }
    }

    public function test_mobile_actions_are_inside_collapsed_details_and_reading_preserves_payload(): void
    {
        [$manager, , $substitute, $swap] = $this->pending();
        app(AcceptShiftSwapAction::class)->execute($substitute, $swap->id);
        $notification = $manager->notifications()->sole();
        $payload = $notification->data;
        $screen = Livewire::actingAs($manager)->test(NotificationsCenter::class);
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$screen->html());
        $xpath = new \DOMXPath($document);
        $cards = $xpath->query('//article[@data-notification-swap]');
        $this->assertCount(1, $cards);
        $card = $cards->item(0);
        $this->assertSame(1, $xpath->query('.//button[not(ancestor::*[@data-notification-details])]', $card)->length);
        $panel = $xpath->query('.//*[@data-notification-details]', $card)->item(0);
        $this->assertTrue($panel->hasAttribute('x-cloak'));
        $this->assertStringContainsString('Marcar como lida', $panel->textContent);
        $this->assertStringContainsString('Abrir módulo', $panel->textContent);
        $this->assertStringContainsString('PDF oficial atualizado', $panel->textContent);
        $this->assertStringContainsString('Imprimir registro histórico', $panel->textContent);
        $this->assertNull($notification->fresh()->read_at);
        $screen->call('markAsRead', $notification->id)->assertSee('Notificação lida')->assertDispatched('notifications-updated');
        $this->assertSame($payload, $notification->fresh()->data);
    }

    public function test_legacy_notification_without_receipt_keeps_module_and_read_controls_without_writes(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $manager->notify(new ShiftSwapEventNotification(['title' => 'Aviso antigo', 'message' => 'Registro original sem comprovante.',
            'target_url' => route('manager.dashboard')]));
        $original = $manager->notifications()->sole()->getAttributes();
        Livewire::actingAs($manager)->test(NotificationsCenter::class)->assertSee('Aviso antigo')
            ->assertSee('Registro original sem comprovante.')->assertSee('Ver detalhes')->assertSee('Abrir módulo')
            ->assertDontSee('Situação atual da troca:')->assertViewHas('swapStatuses', fn ($states) => $states === []);
        $this->assertSame($original, $manager->notifications()->sole()->getAttributes());
    }

    private function assertCurrentPdf(User $viewer, ShiftSwap $swap, ShiftSwapStatus $status): void
    {
        Pdf::shouldReceive('loadView')->once()->with('reports.swap-receipt', \Mockery::on(fn ($data) => $data['receipt']['shift_swap_id'] === $swap->id
            && $data['receipt']['status'] === $status->value && $data['receipt']['status_label'] === $status->label()))
            ->andReturnSelf();
        Pdf::shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        Pdf::shouldReceive('download')->once()->with('comprovante-TROCA-'.str_pad((string) $swap->id, 6, '0', STR_PAD_LEFT).'.pdf')
            ->andReturn(response('PDF de teste', 200, ['Content-Type' => 'application/pdf']));
        $this->actingAs($viewer)->get(route('swaps.receipt.pdf', $swap))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }
}
