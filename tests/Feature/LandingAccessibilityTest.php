<?php

namespace Tests\Feature;

use Tests\TestCase;

class LandingAccessibilityTest extends TestCase
{
    public function test_public_navigation_has_no_links_to_the_authenticated_help(): void
    {
        foreach (['/', '/landing'] as $uri) {
            $this->get($uri)
                ->assertOk()
                ->assertDontSee('/help', false)
                ->assertSee('#superpoderes', false);
        }
    }

    public function test_mobile_menu_is_keyboard_accessible(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('landing-mobile-menu', false)
            ->assertSee('aria-controls="landing-mobile-menu"', false)
            ->assertSee('Fechar menu de navegação', false)
            ->assertSee('keydown.escape', false);
    }

    public function test_landing_respects_reduced_motion(): void
    {
        $landingJs = file_get_contents(resource_path('js/landing.js'));

        $this->assertStringContainsString('prefers-reduced-motion', $landingJs);
        $this->assertStringContainsString('matchMedia', $landingJs);
        $this->assertStringContainsString('showSimulationFinalState', $landingJs);

        $this->assertStringContainsString(
            '@media (prefers-reduced-motion: reduce)',
            file_get_contents(resource_path('css/app.css'))
        );
    }

    public function test_landing_avoids_fixed_narrow_widths_and_low_contrast_microcopy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertDontSee('gsap-floating-mockup w-[310px]', false)
            ->assertDontSee('xs:w-[340px]', false)
            ->assertDontSee('text-slate-400', false)
            ->assertSee('max-w-[310px]', false)
            ->assertSee('scroll-mt-20', false);
    }

    public function test_confirmation_modal_keeps_dialog_semantics(): void
    {
        $modal = file_get_contents(resource_path('views/components/confirmation-modal.blade.php'));

        $this->assertStringContainsString('role="alertdialog"', $modal);
        $this->assertStringContainsString('aria-modal="true"', $modal);
        $this->assertStringContainsString('aria-labelledby', $modal);
        $this->assertStringContainsString('aria-describedby', $modal);
        $this->assertStringContainsString('keydown.escape', $modal);
        $this->assertStringContainsString('focus()', $modal);
    }
}
