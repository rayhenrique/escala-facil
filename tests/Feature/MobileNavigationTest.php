<?php

namespace Tests\Feature;

use App\Enums\ShiftSwapStatus;
use App\Enums\UserRole;
use App\Livewire\MobileNavigation;
use App\Models\ShiftSwap;
use App\Models\User;
use App\Notifications\ShiftSwapEventNotification;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class MobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function menu(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($dom);
    }

    public function test_manager_mobile_destinations_and_menu_preserve_existing_routes(): void
    {
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $response = $this->actingAs($manager)->get(route('manager.dashboard'))->assertOk();
        $xpath = $this->menu($response->getContent());
        $bar = '//nav[@aria-label="Navegação móvel principal"]';
        $this->assertSame(4, $xpath->query($bar.'/a | '.$bar.'/button')->length);
        $this->assertSame(['Início', 'Calendário', 'Trocas'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($xpath->query($bar.'/a'))));
        $links = iterator_to_array($xpath->query('//nav[@aria-label="Outros módulos"]/a'));
        $urls = array_map(fn ($node) => $node->getAttribute('href'), $links);
        foreach (['shifts', 'reports', 'audit', 'sectors', 'categories', 'users', 'issue-reports'] as $module) {
            $this->assertContains(route('manager.'.$module), $urls);
        }
        $this->assertNotContains(route('manager.dashboard'), $urls);
        $this->assertNotContains(route('manager.calendar'), $urls);
        $this->assertNotContains(route('manager.swaps'), $urls);
        $this->actingAs($manager)->get(route('manager.reports', ['tab' => 'coverage']))->assertOk()
            ->assertSee('group:')->assertSee('menu');
    }

    public function test_professional_anchors_menu_and_direct_routes_are_isolated(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get(route('professional.dashboard'))->assertOk();
        $xpath = $this->menu($response->getContent());
        $links = iterator_to_array($xpath->query('//nav[@aria-label="Navegação móvel principal"]/a'));
        $this->assertSame(['#professional-home', '#schedule-title', '#my-swaps-title'], array_map(fn ($node) => $node->getAttribute('href'), $links));
        $this->assertSame(['Início', 'Escala', 'Trocas'], array_map(fn ($node) => trim($node->textContent), $links));
        $menuUrls = array_map(fn ($node) => $node->getAttribute('href'), iterator_to_array($xpath->query('//nav[@aria-label="Outros módulos"]/a')));
        $this->assertContains(route('professional.calendar'), $menuUrls);
        $this->assertContains(route('professional.reports'), $menuUrls);
        $this->assertContains(route('notifications'), $menuUrls);
        foreach ($menuUrls as $url) {
            $this->assertStringNotContainsString('/manager/', $url);
        }
        foreach (['dashboard', 'swaps', 'shifts', 'users', 'audit'] as $module) {
            $this->get(route('manager.'.$module))->assertForbidden();
        }
    }

    public function test_counters_are_scoped_and_refresh_without_writing_history(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        ShiftSwap::factory()->create(['substitute_id' => $user->id]);
        ShiftSwap::factory()->create(['substitute_id' => $other->id]);
        ShiftSwap::factory()->approved()->create(['substitute_id' => $user->id]);
        $user->notify(new ShiftSwapEventNotification(['title' => 'Aviso', 'message' => 'Evento.', 'target_url' => '/notifications']));
        $before = $user->notifications()->sole()->getAttributes();
        $screen = Livewire::actingAs($user)->test(MobileNavigation::class)->assertViewHas('pendingCount', 1)->assertViewHas('unreadCount', 1);
        $screen->call('refreshCounts');
        $this->assertSame($before, $user->notifications()->sole()->getAttributes());
        $user->notifications()->sole()->markAsRead();
        $screen->dispatch('notifications-updated')->assertViewHas('unreadCount', 0);
        ShiftSwap::factory()->create(['status' => ShiftSwapStatus::PendingManager]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        Livewire::actingAs($manager)->test(MobileNavigation::class)->assertViewHas('pendingCount', 1);
    }

    public function test_route_snapshot_is_locked_and_cannot_grant_a_different_profile(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs(User::factory()->create())->test(MobileNavigation::class)->set('routeName', 'manager.users');
    }

    public function test_logout_and_expired_session_do_not_expose_navigation(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('logout'))->assertRedirect(route('login'));
        $this->get(route('professional.dashboard'))->assertRedirect(route('login'));
        $this->get(route('manager.dashboard'))->assertRedirect(route('login'));
        Livewire::test(MobileNavigation::class)->assertForbidden();
    }
}
