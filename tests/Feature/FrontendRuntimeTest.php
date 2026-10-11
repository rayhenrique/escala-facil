<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ApplicationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_operational_screens_only_configure_the_bundled_runtime(): void
    {
        foreach (['/landing', '/login'] as $uri) {
            $this->assertRuntime($uri);
        }
        $this->actingAs(User::factory()->create([
            'role' => UserRole::Manager,
            'last_seen_version' => app(ApplicationVersion::class)->current(),
        ]));
        foreach (['/manager/dashboard', '/manager/shifts', '/manager/swaps', '/manager/reports'] as $uri) {
            $this->assertRuntime($uri);
        }
    }

    private function assertRuntime(string $uri): void
    {
        $response = $this->get($uri)->assertOk()
            ->assertSee('<meta name="mobile-web-app-capable" content="yes">', false)
            ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
            ->assertDontSee('livewire.min.js', false)
            ->assertDontSee('/livewire.js', false);
        $this->assertSame(1, substr_count($response->getContent(), 'window.livewireScriptConfig ='));
    }
}
