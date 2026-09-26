<?php

namespace Tests\Feature\Auth;

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_can_be_requested_and_password_can_be_reset(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->get('/forgot-password')->assertOk()->assertSee('Redefina sua senha');

        Livewire::test(ForgotPassword::class)
            ->set('email', $user->email)
            ->call('sendResetLink')
            ->assertHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user): bool {
            Livewire::test(ResetPassword::class, ['token' => $notification->token])
                ->set('email', $user->email)
                ->set('password', 'nova-senha-segura')
                ->set('password_confirmation', 'nova-senha-segura')
                ->call('resetPassword')
                ->assertHasNoErrors()
                ->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('nova-senha-segura', $user->refresh()->password));
    }
}
