<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ApplicationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProgressiveWebAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_and_authenticated_layouts_expose_the_pwa_metadata_and_install_action(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/manifest.webmanifest', false)
            ->assertSee('apple-mobile-web-app-capable', false)
            ->assertSee('data-pwa-install', false);

        $manager = User::factory()->create([
            'role' => UserRole::Manager,
            'last_seen_version' => app(ApplicationVersion::class)->current(),
        ]);

        $this->actingAs($manager)
            ->get(route('help'))
            ->assertOk()
            ->assertSee('Instalar aplicativo')
            ->assertSee('Como instalar o Escala Fácil')
            ->assertSee('Android com Google Chrome')
            ->assertSee('iPhone ou iPad com Safari')
            ->assertSee('Windows com Microsoft Edge')
            ->assertSee('data-pwa-install', false);
    }

    public function test_manifest_contains_the_required_installability_metadata_and_icons(): void
    {
        $manifest = json_decode(
            file_get_contents(public_path('manifest.webmanifest')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('Escala Fácil', $manifest['name']);
        $this->assertSame('Escala Fácil', $manifest['short_name']);
        $this->assertSame('/dashboard', $manifest['start_url']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertSame('pt-BR', $manifest['lang']);

        $iconsBySize = collect($manifest['icons'])->keyBy('sizes');

        $this->assertTrue($iconsBySize->has('192x192'));
        $this->assertTrue($iconsBySize->has('512x512'));
        $this->assertContains('maskable', collect($manifest['icons'])->pluck('purpose'));

        foreach ($manifest['icons'] as $icon) {
            $path = public_path(ltrim($icon['src'], '/'));

            $this->assertFileExists($path);

            [$width, $height] = getimagesize($path);
            [$expectedWidth, $expectedHeight] = array_map('intval', explode('x', $icon['sizes']));

            $this->assertSame($expectedWidth, $width);
            $this->assertSame($expectedHeight, $height);
        }
    }

    public function test_service_worker_uses_a_safe_offline_fallback_without_caching_authenticated_pages(): void
    {
        $serviceWorker = file_get_contents(public_path('sw.js'));
        $navigationStrategy = str($serviceWorker)
            ->after("if (request.mode === 'navigate')")
            ->before('const isStaticAsset')
            ->toString();

        $this->assertStringContainsString('caches.match(OFFLINE_URL)', $navigationStrategy);
        $this->assertStringNotContainsString('cache.put', $navigationStrategy);
        $this->assertStringContainsString("url.pathname.startsWith('/build/assets/')", $serviceWorker);
        $this->assertStringContainsString("navigator.serviceWorker.register('/sw.js'", file_get_contents(resource_path('js/app.js')));

        $offlinePage = file_get_contents(public_path('offline.html'));

        $this->assertStringContainsString('Você está sem conexão', $offlinePage);
        $this->assertStringContainsString('não ficam armazenados para consulta offline', $offlinePage);

        $deployScript = file_get_contents(base_path('scripts/deploy-production.sh'));

        $this->assertStringContainsString('/manifest.webmanifest', $deployScript);
        $this->assertStringContainsString('/sw.js', $deployScript);
    }
}
