<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\Event;
use Sentry\EventHint;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Sentry\UserDataBag;
use Throwable;

class ErrorReporting
{
    /**
     * Chaves nunca enviadas a serviços externos nem gravadas em contexto de erro.
     * Comparação exata, sem diferenciar maiúsculas de minúsculas.
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'pass',
        'pwd',
        'token',
        '_token',
        'remember_token',
        'api_token',
        'access_token',
        'refresh_token',
        'reset_token',
        'secret',
        'client_secret',
        'api_key',
        'apikey',
        'authorization',
        'proxy_authorization',
        'cookie',
        'cookies',
        'set-cookie',
        'session',
        'session_id',
        'x-xsrf-token',
        'xsrf-token',
        'cpf',
        'cnpj',
        'rg',
        'cns',
        'sus_card',
        'phone',
        'telefone',
        'celular',
        'address',
        'endereco',
        'birth',
        'nascimento',
        'card_number',
        'credit_card',
        'cvv',
    ];

    public const FILTERED = '[Filtrado]';

    public static function enabled(): bool
    {
        return is_string(config('sentry.dsn')) && config('sentry.dsn') !== '';
    }

    /**
     * Registra a exceção com contexto depurado e correlaciona com o Sentry.
     * Nunca lança: a observabilidade não pode quebrar a aplicação.
     */
    public function log(Throwable $throwable, array $extra = []): string
    {
        $errorId = (string) Str::ulid();

        try {
            Log::error(
                'Exceção capturada: '.$throwable::class.': '.$throwable->getMessage(),
                array_merge($this->baseContext(), ['error_id' => $errorId], self::scrub($extra))
            );
        } catch (Throwable) {
            // Silencioso: falhar ao registrar não pode agravar a falha original.
        }

        $this->tagScope($errorId);

        return $errorId;
    }

    /**
     * Registra um erro JavaScript já depurado vindo do navegador.
     */
    public function logClientError(array $payload): string
    {
        $errorId = (string) Str::ulid();

        try {
            Log::warning(
                'Erro JavaScript: '.mb_substr((string) ($payload['message'] ?? 'sem mensagem'), 0, 500),
                array_merge($this->baseContext(), [
                    'error_id' => $errorId,
                    'source' => 'javascript',
                    'page' => mb_substr((string) ($payload['page'] ?? '/'), 0, 255),
                    'script' => mb_substr((string) ($payload['source'] ?? ''), 0, 255),
                    'line' => $payload['line'] ?? null,
                    'column' => $payload['column'] ?? null,
                ])
            );
        } catch (Throwable) {
            // Silencioso: falhar ao registrar não pode agravar a falha original.
        }

        $this->tagScope($errorId);

        return $errorId;
    }

    /**
     * Callback `before_send` do Sentry: remove segredos e PII desnecessária.
     * Nunca retorna null e nunca lança.
     */
    public static function scrubEvent(Event $event, ?EventHint $hint = null): ?Event
    {
        unset($hint);

        try {
            $request = $event->getRequest();

            if (isset($request['cookies']) && is_array($request['cookies'])) {
                $request['cookies'] = array_map(fn (): string => self::FILTERED, $request['cookies']);
            }

            if (isset($request['headers']) && is_array($request['headers'])) {
                $request['headers'] = self::scrub($request['headers']);
            }

            if (isset($request['data']) && is_array($request['data'])) {
                $request['data'] = self::scrub($request['data']);
            }

            if (isset($request['url']) && is_string($request['url'])) {
                $request['url'] = self::scrubUrl($request['url']);
            }

            unset($request['query_string']);
            $event->setRequest($request);

            $user = $event->getUser();
            $event->setUser(new UserDataBag($user?->getId()));

            $event->setTag('app.version', (string) config('version.current', 'desconhecida'));
        } catch (Throwable) {
            // Mantém o evento original em caso de falha no expurgo.
        }

        return $event;
    }

    /** @return array<string, mixed> */
    public static function scrub(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            $name = mb_strtolower((string) $key);

            if (in_array($name, self::SENSITIVE_KEYS, true)) {
                $clean[$key] = self::FILTERED;

                continue;
            }

            $clean[$key] = is_array($value) ? self::scrub($value) : $value;
        }

        return $clean;
    }

    public static function scrubUrl(string $url): string
    {
        $path = strtok($url, '?') ?: $url;

        return (string) preg_replace('#[A-Za-z0-9\\-_]{20,}#', '[redacted]', $path);
    }

    /** @return array<string, mixed> */
    private function baseContext(): array
    {
        return [
            'app_version' => (string) config('version.current', 'desconhecida'),
            'route' => $this->currentRoute(),
            'role' => auth()->user()?->role?->value ?? 'visitante',
            'user_id' => auth()->id(),
            'time' => now()->toIso8601String(),
        ];
    }

    private function currentRoute(): string
    {
        try {
            return request()->route()?->getName() ?? 'desconhecida';
        } catch (Throwable) {
            return 'desconhecida';
        }
    }

    private function tagScope(string $errorId): void
    {
        if (! self::enabled() || ! class_exists(SentrySdk::class)) {
            return;
        }

        try {
            \Sentry\configureScope(function (Scope $scope) use ($errorId): void {
                $scope->setTag('error_id', $errorId);
                $scope->setTag('app.version', (string) config('version.current', 'desconhecida'));

                if (auth()->check()) {
                    $scope->setUser(['id' => (string) auth()->id()]);
                    $scope->setTag('user.role', (string) (auth()->user()?->role?->value ?? 'desconhecido'));
                }
            });
        } catch (Throwable) {
            // Silencioso: falhar ao marcar o escopo não pode agravar a falha original.
        }
    }
}
