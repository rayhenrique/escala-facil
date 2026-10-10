<?php

namespace Tests\Feature;

use App\Actions\ShiftSwaps\AcceptShiftSwapAction;
use App\Actions\ShiftSwaps\CreateShiftSwapAction;
use App\Actions\ShiftSwaps\DecideShiftSwapAction;
use App\Enums\ShiftStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Sector;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_route_resolves_to_expected_path_and_legacy_path_does_not_exist(): void
    {
        $this->assertSame('/notifications', route('notifications', [], false));
        $this->assertStringEndsWith('/notifications', route('notifications'));

        $this->get('/notificacoes')->assertNotFound();
    }

    public function test_authenticated_users_can_open_notifications_center(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);

        $this->get('/notifications')->assertRedirect(route('login'));

        $this->actingAs($manager)
            ->get(route('notifications'))
            ->assertOk()
            ->assertSee('Notificações', false);
    }

    public function test_shift_swap_notifications_point_to_notifications_center(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);

        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);

        $professionalNotification = $requester->notifications()
            ->where('data->event', 'accepted')
            ->firstOrFail();
        $this->assertSame('/notifications', $professionalNotification->data['target_url']);

        $managerNotification = $manager->notifications()->sole();
        $this->assertSame('/manager/swaps', $managerNotification->data['target_url']);

        app(DecideShiftSwapAction::class)->approve($manager, $shiftSwap->id);

        foreach ([$requester, $substitute] as $participant) {
            $approval = $participant->refresh()->notifications()
                ->where('data->event', 'approved')
                ->firstOrFail();
            $this->assertSame('/notifications', $approval->data['target_url']);
        }

        $this->assertDatabaseMissing('notifications', ['id' => $managerNotification->id, 'data' => '%/notificacoes%']);
    }

    public function test_rejected_and_reverted_notifications_point_to_notifications_center(): void
    {
        [$manager, $requester, $substitute, $shift] = $this->team();

        $shiftSwap = app(CreateShiftSwapAction::class)->execute($requester, [
            'shift_id' => $shift->id,
            'substitute_id' => $substitute->id,
        ]);
        app(AcceptShiftSwapAction::class)->execute($substitute, $shiftSwap->id);
        app(DecideShiftSwapAction::class)->reject($manager, $shiftSwap->id, 'Sem cobertura suficiente.');

        // Participantes recebem o evento histórico de rejeição apontando para a Central.
        foreach ([$requester, $substitute] as $participant) {
            $rejection = $participant->refresh()->notifications()
                ->where('data->event', 'rejected')
                ->firstOrFail();

            $this->assertSame('/notifications', $rejection->data['target_url']);
            $this->assertStringNotContainsString('/notificacoes', json_encode($rejection->data));
        }

        // O histórico da gerência permanece imutável como "aguardando aprovação";
        // o estado atual (Rejeitada) é exibido em tempo de execução na Central.
        $managerNotification = $manager->refresh()->notifications()->sole();
        $this->assertSame('accepted', $managerNotification->data['event']);
        $this->assertSame('Troca aguardando aprovação', $managerNotification->data['title']);
        $this->assertSame('/manager/swaps', $managerNotification->data['target_url']);
        $this->assertSame('Aguardando gerência', $managerNotification->data['receipt']['status_label']);
        $this->assertStringNotContainsString('/notificacoes', json_encode($managerNotification->data));
    }

    public function test_pwa_notification_assets_point_to_notifications_center(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));
        $serviceWorker = file_get_contents(public_path('sw.js'));
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        // Nenhuma referência à rota inexistente pode permanecer no bundle ou no service worker.
        $this->assertStringNotContainsString('/notificacoes', $appJs);
        $this->assertStringNotContainsString('/notificacoes', $serviceWorker);

        // O aviso do dispositivo abre a Central de Notificações.
        $this->assertStringContainsString("data: { url: '/notifications' }", $appJs);
        $this->assertStringContainsString("|| '/notifications'", $serviceWorker);

        // Clicar na notificação resolve a URL absoluta (funciona com o PWA instalado),
        // reaproveita a janela aberta ou abre uma nova.
        $this->assertStringContainsString('notificationclick', $serviceWorker);
        $this->assertStringContainsString('new URL(rawTargetUrl, self.location.origin)', $serviceWorker);
        $this->assertStringContainsString('clients.matchAll', $serviceWorker);
        $this->assertStringContainsString('client.navigate(targetUrl)', $serviceWorker);
        $this->assertStringContainsString('client.focus()', $serviceWorker);
        $this->assertStringContainsString('clients.openWindow(targetUrl)', $serviceWorker);

        // O funcionamento atual de badge e notificações precisa ser preservado.
        $this->assertStringContainsString('syncAppBadge', $appJs);
        $this->assertStringContainsString('setAppBadge', $appJs);
        $this->assertStringContainsString('showNotification', $appJs);
        $this->assertStringContainsString("tag: 'escala-facil-notif'", $appJs);

        // Atalho do manifesto continua apontando para a rota válida.
        $shortcutUrls = collect($manifest['shortcuts'] ?? [])->pluck('url')->all();
        $this->assertContains('/notifications', $shortcutUrls);
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
