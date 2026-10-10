<?php

namespace Tests\Feature;

use Tests\TestCase;

class DesignUnityTest extends TestCase
{
    public function test_landing_uses_the_system_design_tokens(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('bg-primary', false)
            ->assertSee('text-ink', false)
            ->assertSee('text-muted', false)
            ->assertSee('bg-canvas', false)
            ->assertSee('border-line', false);
    }

    public function test_landing_avoids_generic_saas_decoration(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['bg-gradient-', 'backdrop-blur', 'blur-3xl', 'animate-ping', 'hover:-translate'] as $decor) {
            $response->assertDontSee($decor, false);
        }

        foreach (['emerald-', 'teal-'] as $palette) {
            $response->assertDontSee($palette, false);
        }
    }

    public function test_design_documents_cover_the_unified_landing(): void
    {
        $design = file_get_contents(base_path('DESIGN.md'));
        $contract = file_get_contents(base_path('UX-CONTRACT.md'));

        $this->assertStringContainsString('landing', strtolower($design));
        $this->assertStringContainsString('prefers-reduced-motion', $design);
        $this->assertStringContainsString('landing', strtolower($contract));
    }
}
