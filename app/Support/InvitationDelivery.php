<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class InvitationDelivery
{
    public static function mailer(): string
    {
        $connection = config('queue.default');
        if (! is_string($connection) || in_array(config('queue.connections.'.$connection.'.driver'), [null, 'null'], true)) {
            throw ValidationException::withMessages(['invitation' => 'Configure uma fila disponível ou a conexão sync para enviar convites.']);
        }
        $mailer = config('invitations.mailer') ?: config('mail.default');
        if (! is_string($mailer) || ! self::safe($mailer)) {
            throw ValidationException::withMessages(['invitation' => 'Configure um transporte de e-mail para convites. Links de ativação não podem ser enviados ao log nem descartados em produção.']);
        }

        return $mailer;
    }

    private static function safe(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return false;
        }
        $transport = config('mail.mailers.'.$mailer.'.transport');
        if (! is_string($transport) || in_array($transport, ['log', 'null'], true) || ($transport === 'array' && app()->isProduction())) {
            return false;
        }
        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $children = config('mail.mailers.'.$mailer.'.mailers', []);
            if ($children === []) {
                return false;
            }
            foreach ($children as $child) {
                if (! is_string($child) || ! self::safe($child, [...$visited, $mailer])) {
                    return false;
                }
            }
        }

        return true;
    }
}
