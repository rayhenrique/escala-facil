<?php

namespace Tests\Feature;

use App\Actions\Accounts\ActivateAccountAction;
use App\Actions\Accounts\InviteProfessionalAction;
use App\Enums\UserRole;
use App\Http\Requests\SaveUserRequest;
use App\Livewire\Auth\ActivateAccount;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Manager\UserManager;
use App\Models\AccountInvitation;
use App\Models\Category;
use App\Models\OperationalAuditEvent;
use App\Models\Sector;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Notifications\ResetPassword as ResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AccountInvitationsTest extends TestCase
{
    use RefreshDatabase;

    private function team(): array
    {
        Notification::fake();
        config(['mail.default' => 'array', 'invitations.mailer' => null]);
        $manager = User::factory()->create(['role' => UserRole::Manager]);
        $sector = Sector::factory()->create();
        $category = Category::factory()->create();
        $payload = ['name' => 'Ana Convidada', 'email' => 'ana@example.test', 'role' => 'professional', 'sector_id' => $sector->id, 'category_id' => $category->id];

        return [$manager, $payload];
    }

    private function token(AccountInvitation $invitation): string
    {
        $notification = Notification::sent($invitation->user, AccountInvitationNotification::class)->last();
        parse_str(parse_url($notification->toMail($invitation->user)->actionUrl, PHP_URL_QUERY), $query);

        return $query['token'];
    }

    private function invalid(string $hash): void
    {
        try {
            app(ActivateAccountAction::class)->execute($hash, ['password' => 'Minha-senha-2026', 'password_confirmation' => 'Minha-senha-2026']);
            $this->fail('Invalid invitation must not activate an account.');
        } catch (ValidationException $exception) {
            $this->assertSame([ActivateAccountAction::INVALID], $exception->errors()['invitation']);
        }
    }

    public function test_manager_invites_new_professional_without_exposing_a_password_or_plain_token(): void
    {
        [$manager, $payload] = $this->team();
        $existing = User::factory()->create();
        $password = $existing->password;
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload + ['password' => null, 'password_confirmation' => null]);
        $token = $this->token($invitation);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertTrue($invitation->user->requires_activation);
        $this->assertFalse(Hash::check('password', $invitation->user->password));
        $this->assertSame('Pendente', $invitation->status($invitation->user));
        $this->assertArrayNotHasKey('token_hash', $invitation->toArray());
        $events = OperationalAuditEvent::where('entity_type', 'invitation')->get()->toJson();
        $this->assertStringNotContainsString($token, $events);
        $this->assertStringNotContainsString($invitation->token_hash, $events);
        $this->assertStringNotContainsString('email_hash', $events);
        $this->assertSame($password, $existing->fresh()->password);
        $this->assertFalse($existing->fresh()->requires_activation);
        $this->get('/register')->assertNotFound();
    }

    public function test_activation_defines_own_password_and_consumes_token_once(): void
    {
        [$manager, $payload] = $this->team();
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload);
        $hash = hash('sha256', $this->token($invitation));
        Password::createToken($invitation->user);
        $grant = str_repeat('a', 32);
        $this->withSession(['account_activation_grants' => [$grant => $hash]]);
        Livewire::withQueryParams(['grant' => $grant])->test(ActivateAccount::class)->set('password', 'Minha-senha-2026')->set('password_confirmation', 'Minha-senha-2026')
            ->call('activate')->assertHasNoErrors()->assertRedirect(route('login'));
        $this->assertFalse($invitation->user->fresh()->requires_activation);
        $this->assertTrue(Hash::check('Minha-senha-2026', $invitation->user->fresh()->password));
        $this->assertNull($invitation->fresh()->token_hash);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $payload['email']]);
        $this->assertSame('Aceito', $invitation->fresh()->status($invitation->user->fresh()));
        $this->assertGuest();
        $this->invalid($hash);
        $event = OperationalAuditEvent::where('entity_type', 'invitation')->where('action', 'activated')->sole();
        $this->assertSame($invitation->user_id, $event->author_id);
        Livewire::test(Login::class)->set('form.email', $payload['email'])->set('form.password', 'Minha-senha-2026')->call('login')->assertHasNoErrors()->assertRedirect('/dashboard');
    }

    public function test_expiration_at_exact_boundary_is_refused_without_any_activation_write(): void
    {
        [$manager, $payload] = $this->team();
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload);
        $hash = hash('sha256', $this->token($invitation));
        $this->travelTo($invitation->expires_at);
        $count = OperationalAuditEvent::count();
        $this->invalid($hash);
        $this->assertSame('Expirado', $invitation->fresh()->status($invitation->user));
        $this->assertTrue($invitation->user->fresh()->requires_activation);
        $this->assertSame($count, OperationalAuditEvent::count());
    }

    public function test_resend_rotates_token_and_revocation_invalidates_without_removing_user(): void
    {
        [$manager, $payload] = $this->team();
        $action = app(InviteProfessionalAction::class);
        $invitation = $action->create($manager, $payload);
        $old = $this->token($invitation);
        $oldNotification = Notification::sent($invitation->user, AccountInvitationNotification::class)->last();
        $action->resend($manager, $invitation->user_id);
        $new = $this->token($invitation->fresh());
        $this->assertNotSame($old, $new);
        $this->assertFalse($oldNotification->shouldSend($invitation->user, 'mail'));
        $this->invalid(hash('sha256', $old));
        $action->revoke($manager, $invitation->user_id);
        $this->invalid(hash('sha256', $new));
        $this->assertSame('Revogado', $invitation->fresh()->status($invitation->user));
        $this->assertNotNull($invitation->user->fresh());
        $this->assertDatabaseCount('account_invitations', 1);
        $action->resend($manager, $invitation->user_id);
        $this->assertSame('Pendente', $invitation->fresh()->status($invitation->user));
        $this->assertDatabaseHas('operational_audit_events', ['entity_type' => 'invitation', 'action' => 'resent']);
        $this->assertDatabaseHas('operational_audit_events', ['entity_type' => 'invitation', 'action' => 'revoked']);
    }

    public function test_pending_account_cannot_login_or_use_password_recovery_as_activation(): void
    {
        [$manager, $payload] = $this->team();
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload);
        $user = $invitation->user;
        $user->update(['password' => 'Known-test-password']);
        Livewire::test(Login::class)->set('form.email', $user->email)->set('form.password', 'Known-test-password')->call('login')->assertHasErrors('form.email');
        Livewire::test(ForgotPassword::class)->set('email', $user->email)->call('sendResetLink')->assertHasNoErrors()->assertSee('Se houver uma conta ativa');
        Notification::assertNotSentTo($user, ResetNotification::class);
        $token = Password::createToken($user);
        Livewire::test(ResetPassword::class, ['token' => $token])->set('email', $user->email)->set('password', 'Reset-new-password')->set('password_confirmation', 'Reset-new-password')->call('resetPassword')->assertHasErrors('email');
        $this->assertTrue($user->fresh()->requires_activation);
        $this->assertTrue(Hash::check('Known-test-password', $user->fresh()->password));
        $this->assertGuest();
    }

    public function test_recovery_messages_do_not_reveal_missing_or_pending_accounts(): void
    {
        [$manager, $payload] = $this->team();
        $pending = app(InviteProfessionalAction::class)->create($manager, $payload)->user;
        $active = User::factory()->create();
        foreach ([$pending->email, $active->email, 'missing@example.test'] as $email) {
            Livewire::test(ForgotPassword::class)->set('email', $email)->call('sendResetLink')->assertHasNoErrors()->assertSee('Se houver uma conta ativa para este e-mail');
        }
    }

    public function test_invitation_url_is_cleaned_and_does_not_expose_identity_or_raw_token_to_livewire(): void
    {
        [$manager, $payload] = $this->team();
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload);
        $token = $this->token($invitation);
        $response = $this->get(route('account.activate', ['token' => $token]))->assertStatus(302)->assertHeader('Referrer-Policy', 'no-referrer');
        parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $response->assertSessionHas('account_activation_grants.'.$query['grant'], hash('sha256', $token));
        $page = $this->get($response->headers->get('Location'))->assertOk()->assertDontSee($token)->assertDontSee($payload['email'])->assertDontSee($payload['name']);
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->get(route('account.activate', ['token' => 'invalid']))->assertRedirect(route('account.activate'));
        $this->get(route('account.activate'))->assertOk()->assertSee('Crie sua senha');
    }

    public function test_professionals_and_demoted_managers_cannot_create_resend_or_revoke(): void
    {
        [$manager, $payload] = $this->team();
        $action = app(InviteProfessionalAction::class);
        $invitation = $action->create($manager, $payload);
        $professional = User::factory()->create();
        foreach ([fn () => $action->create($professional, $payload), fn () => $action->resend($professional, $invitation->user_id), fn () => $action->revoke($professional, $invitation->user_id)] as $attempt) {
            try {
                $attempt();
                $this->fail('Professional must not manage invitations.');
            } catch (AuthorizationException) {
            }
        }
        Livewire::actingAs($professional)->test(UserManager::class)->assertForbidden();
        $manager->update(['role' => UserRole::Professional]);
        $this->expectException(AuthorizationException::class);
        $action->resend($manager, $invitation->user_id);
    }

    public function test_existing_users_cannot_be_replaced_and_accepted_accounts_cannot_be_reinvited(): void
    {
        [$manager, $payload] = $this->team();
        $action = app(InviteProfessionalAction::class);
        $invitation = $action->create($manager, $payload);
        try {
            $action->create($manager, $payload);
            $this->fail('Existing account must not be overwritten.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
        }
        app(ActivateAccountAction::class)->execute(hash('sha256', $this->token($invitation)), ['password' => 'Minha-senha-2026', 'password_confirmation' => 'Minha-senha-2026']);
        $this->expectException(AuthorizationException::class);
        $action->resend($manager, $invitation->user_id);
    }

    public function test_email_change_invalidates_link_and_pending_password_cannot_be_set_by_manager(): void
    {
        [$manager, $payload] = $this->team();
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload);
        $token = $this->token($invitation);
        $invitation->user->update(['email' => 'new@example.test']);
        $this->invalid(hash('sha256', $token));
        $this->assertSame('Revogado', $invitation->fresh()->status($invitation->user->fresh()));
        try {
            (new SaveUserRequest)->validatePayload($payload + ['user_id' => $invitation->user_id, 'password' => 'Manager-password', 'password_confirmation' => 'Manager-password']);
            $this->fail('Only the professional should define the activation password.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('role', $exception->errors());
        }
    }

    public function test_livewire_invite_statuses_and_revocation_confirmation(): void
    {
        [$manager, $payload] = $this->team();
        $component = Livewire::actingAs($manager)->test(UserManager::class)->call('openInvitationModal')->assertDontSee('id="user-password"', false)
            ->set('name', $payload['name'])->set('email', $payload['email'])->set('sectorId', (string) $payload['sector_id'])->set('categoryId', (string) $payload['category_id'])
            ->call('save')->assertHasNoErrors()->assertSee('Convite: Pendente');
        $user = User::where('email', $payload['email'])->sole();
        $component->call('requestInvitationRevocation', $user->id)->assertSee('Revogar convite?')->call('confirmInvitationRevocation')->assertHasNoErrors()->assertSee('Convite: Revogado');
        $component->call('resendInvitation', $user->id)->assertHasNoErrors()->assertSee('Convite: Pendente');
    }

    public function test_browser_grants_are_isolated_between_tabs_and_cannot_be_shared_without_session(): void
    {
        [$manager, $payload] = $this->team();
        $first = app(InviteProfessionalAction::class)->create($manager, $payload);
        $second = app(InviteProfessionalAction::class)->create($manager, array_replace($payload, ['email' => 'second@example.test']));
        $grants = [];
        foreach ([$first, $second] as $invitation) {
            $response = $this->get(route('account.activate', ['token' => $this->token($invitation)]));
            parse_str(parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
            $grants[] = $query['grant'];
        }
        Livewire::withQueryParams(['grant' => $grants[0]])->test(ActivateAccount::class)->set('password', 'First-password')->set('password_confirmation', 'First-password')->call('activate')->assertHasNoErrors();
        $this->assertFalse($first->user->fresh()->requires_activation);
        $this->assertTrue($second->user->fresh()->requires_activation);
        $this->withSession(['account_activation_grants' => []]);
        Livewire::withQueryParams(['grant' => $grants[1]])->test(ActivateAccount::class)->set('password', 'Second-password')->set('password_confirmation', 'Second-password')->call('activate')->assertHasErrors('invitation');
        $this->assertTrue($second->user->fresh()->requires_activation);
    }

    public function test_log_transport_is_refused_before_creating_an_account_or_token(): void
    {
        [$manager, $payload] = $this->team();
        config(['invitations.mailer' => 'log']);
        try {
            app(InviteProfessionalAction::class)->create($manager, $payload);
            $this->fail('Invitation tokens must not be sent to application logs.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invitation', $exception->errors());
        }
        $this->assertDatabaseCount('account_invitations', 0);
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
    }

    public function test_management_list_eager_loads_invitation_status_without_per_row_queries(): void
    {
        [$manager, $payload] = $this->team();
        for ($i = 0; $i < 10; $i++) {
            app(InviteProfessionalAction::class)->create($manager, array_replace($payload, ['email' => 'user'.$i.'@example.test']));
        }
        DB::enableQueryLog();
        $this->actingAs($manager)->get(route('manager.users'))->assertOk()->assertSee('Convite: Pendente');
        $queries = collect(DB::getQueryLog())->pluck('query');
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(4, $queries->filter(fn ($query) => str_contains($query, 'from "users"'))->count());
        $this->assertSame(1, $queries->filter(fn ($query) => str_contains($query, 'from "account_invitations"'))->count());
    }

    public function test_disabled_queue_is_refused_and_migration_cannot_release_pending_accounts(): void
    {
        [$manager, $payload] = $this->team();
        $connection = config('queue.default');
        config(['queue.default' => 'null']);
        try {
            app(InviteProfessionalAction::class)->create($manager, $payload);
            $this->fail('Disabled queue must not silently discard invitations.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invitation', $exception->errors());
        }
        $this->assertDatabaseCount('account_invitations', 0);
        config(['queue.default' => $connection]);
        $invitation = app(InviteProfessionalAction::class)->create($manager, $payload);
        $migration = require database_path('migrations/2026_10_10_233000_create_account_invitations_table.php');
        try {
            $migration->down();
            $this->fail('Rollback must not remove the pending login restriction.');
        } catch (\LogicException) {
        }
        $this->assertTrue($invitation->user->fresh()->requires_activation);
        $this->assertDatabaseCount('account_invitations', 1);
    }
}
