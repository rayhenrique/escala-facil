<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\ErrorReporting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Sentry\Event;
use Sentry\UserDataBag;
use Tests\TestCase;

class ErrorReportingTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_keys_are_scrubbed_recursively(): void
    {
        $clean = ErrorReporting::scrub([
            'email' => 'ana@escalafacil.test',
            'password' => 'segredo-123',
            'PASSWORD_CONFIRMATION' => 'segredo-123',
            'api_token' => 'token-sensivel',
            'nested' => [
                'cpf' => '12345678900',
                'sector' => 'Emergência',
            ],
        ]);

        $this->assertSame('ana@escalafacil.test', $clean['email']);
        $this->assertSame('Emergência', $clean['nested']['sector']);
        $this->assertSame(ErrorReporting::FILTERED, $clean['password']);
        $this->assertSame(ErrorReporting::FILTERED, $clean['PASSWORD_CONFIRMATION']);
        $this->assertSame(ErrorReporting::FILTERED, $clean['api_token']);
        $this->assertSame(ErrorReporting::FILTERED, $clean['nested']['cpf']);
    }

    public function test_scrub_url_removes_query_strings_and_token_like_segments(): void
    {
        $this->assertSame(
            'http://localhost/password/reset',
            ErrorReporting::scrubUrl('http://localhost/password/reset?token=segredo&email=a@b.c')
        );
        $this->assertStringNotContainsString(
            'abc123DEF456ghi789JKL',
            ErrorReporting::scrubUrl('/swaps/abc123DEF456ghi789JKL/receipt/pdf')
        );
    }

    public function test_reporting_is_disabled_without_a_dsn(): void
    {
        $this->assertFalse(ErrorReporting::enabled());
    }

    public function test_sentry_event_scrub_keeps_only_the_user_id(): void
    {
        $event = Event::createEvent();
        $event->setRequest([
            'url' => 'http://localhost/login?token=segredo',
            'cookies' => ['session' => 'abc'],
            'headers' => ['Authorization' => 'Bearer segredo', 'Accept' => 'text/html'],
            'data' => ['password' => 'segredo', 'email' => 'a@b.c'],
        ]);
        $event->setUser(new UserDataBag(id: 7, email: 'a@b.c', username: 'ana'));

        $scrubbed = ErrorReporting::scrubEvent($event);

        $this->assertNotNull($scrubbed);
        $request = $scrubbed->getRequest();
        $this->assertSame(['session' => ErrorReporting::FILTERED], $request['cookies']);
        $this->assertSame(ErrorReporting::FILTERED, $request['headers']['Authorization']);
        $this->assertSame(ErrorReporting::FILTERED, $request['data']['password']);
        $this->assertSame('a@b.c', $request['data']['email']);
        $this->assertArrayNotHasKey('query_string', $request);
        $this->assertSame(7, $scrubbed->getUser()?->getId());
        $this->assertNull($scrubbed->getUser()?->getEmail());
    }

    public function test_log_returns_an_error_id_without_recording_secrets(): void
    {
        Log::shouldReceive('error')->once()->withArgs(
            function (string $message, array $context): bool {
                $payload = json_encode($context);

                return str_contains($message, RuntimeException::class)
                    && isset($context['error_id'], $context['app_version'], $context['route'], $context['role'])
                    && ! str_contains($payload, 'senha-super-secreta');
            }
        );

        $errorId = app(ErrorReporting::class)->log(
            new RuntimeException('Falha simulada.'),
            ['password' => 'senha-super-secreta']
        );

        $this->assertNotEmpty($errorId);
    }

    public function test_client_error_endpoint_accepts_scrubbed_payloads(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        $this->post(route('support.client-error'), [
            'message' => 'Cannot read properties of null',
            'source' => 'app.js',
            'line' => 12,
            'column' => 3,
            'page' => '/notifications',
        ])->assertRedirect(route('login'));

        $this->actingAs($professional)
            ->post(route('support.client-error'), ['source' => 'app.js'])
            ->assertSessionHasErrors(['message']);

        $this->actingAs($professional)
            ->post(route('support.client-error'), [
                'message' => 'Cannot read properties of null',
                'source' => 'app.js',
                'page' => '/notifications',
            ])
            ->assertNoContent();
    }

    public function test_client_error_endpoint_keeps_only_paths(): void
    {
        $professional = User::factory()->create(['role' => UserRole::Professional]);

        Log::shouldReceive('warning')->once()->withArgs(
            function (string $message, array $context): bool {
                return isset($context['error_id'], $context['source'])
                    && $context['source'] === 'javascript'
                    && $context['page'] === '/notifications'
                    && ! str_contains(json_encode($context), 'token=segredo');
            }
        );

        $this->actingAs($professional)
            ->post(route('support.client-error'), [
                'message' => 'Falha simulada no navegador.',
                'source' => 'app.js',
                'page' => 'https://app.exemplo/notifications?token=segredo',
            ])
            ->assertNoContent();
    }
}
