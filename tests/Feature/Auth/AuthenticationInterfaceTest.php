<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authentication_pages_keep_native_password_semantics_and_mobile_metadata(): void
    {
        foreach (['/login', '/forgot-password', '/reset-password/example?email=test@example.test'] as $uri) {
            $response = $this->get($uri)->assertOk()->assertSee('data-auth-form', false)
                ->assertSee('novalidate', false)->assertSee('inputmode="email"', false)
                ->assertSee('autocapitalize="none"', false)->assertSee('interactive-widget=resizes-content', false)
                ->assertDontSee('autofocus', false)->assertSee('data-pwa-ios hidden', false)
                ->assertSee('Instalação opcional')->assertSee('auth-install-action', false);
            if ($uri !== '/forgot-password') {
                $response->assertSee('type="password"', false)->assertSee('name="password"', false)
                    ->assertSee('aria-controls="password"', false);
            }
        }
        $this->get('/login')->assertSee('autocomplete="current-password"', false);
        $this->get('/reset-password/example')->assertSee('autocomplete="new-password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_validation_messages_remain_associated_and_recovery_confirmation_is_inline(): void
    {
        Livewire::test(Login::class)->call('login')->assertHasErrors(['form.email', 'form.password'])
            ->assertSee('aria-describedby="email-error"', false)
            ->assertSee('aria-describedby="password-error"', false);
        Notification::fake();
        $user = User::factory()->create();
        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('sendResetLink')
            ->assertHasNoErrors()->assertSee('auth-session-status', false)->assertSee('role="status"', false);
    }

    public function test_remember_and_session_regeneration_keep_the_existing_login_policy(): void
    {
        $user = User::factory()->create();
        session()->start();
        $before = session()->getId();
        Livewire::test(Login::class)->set('form.email', $user->email)->set('form.password', 'password')
            ->set('form.remember', true)->call('login')->assertHasNoErrors()->assertRedirect('/dashboard');
        $this->assertNotSame($before, session()->getId());
        $this->assertTrue(Cookie::hasQueued(Auth::guard()->getRecallerName()));
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_attempt_limit_still_blocks_the_sixth_attempt_without_authenticating(): void
    {
        $user = User::factory()->create();
        $key = Str::transliterate(Str::lower($user->email).'|127.0.0.1');
        try {
            $component = Livewire::test(Login::class)->set('form.email', $user->email)->set('form.password', 'invalid');
            for ($i = 0; $i < 5; $i++) {
                $component->call('login')->assertHasErrors(['form.email']);
            }
            $component->set('form.password', 'password')->call('login')->assertHasErrors(['form.email']);
            $this->assertGuest();
            $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        } finally {
            RateLimiter::clear($key);
        }
    }

    public function test_mismatched_password_and_invalid_reset_token_do_not_change_existing_credentials(): void
    {
        $user = User::factory()->create();
        $before = $user->password;
        $component = Livewire::test(ResetPassword::class, ['token' => 'invalid'])
            ->set('email', $user->email)->set('password', 'new-password');
        $component->set('password_confirmation', 'different')->call('resetPassword')
            ->assertHasErrors(['password' => 'confirmed'])->assertSee('aria-describedby="password-error"', false);
        $component->set('password_confirmation', 'new-password')->call('resetPassword')->assertHasErrors(['email']);
        $this->assertSame($before, $user->fresh()->password);
        $this->assertGuest();
    }
}
