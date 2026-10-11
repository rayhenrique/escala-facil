<?php

namespace App\Notifications;

use App\Models\AccountInvitation;
use App\Models\User;
use App\Support\InvitationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountInvitationNotification extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(private int $invitationId, #[\SensitiveParameter] private string $token)
    {
        $this->afterCommit();
    }

    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        $invitation = AccountInvitation::find($this->invitationId);
        $user = $notifiable->fresh();
        if (! $user || ! $invitation || ! $invitation->usable($user) || ! hash_equals($invitation->token_hash, hash('sha256', $this->token))) {
            return false;
        }
        InvitationDelivery::mailer();

        return true;
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)->mailer(InvitationDelivery::mailer())->subject('Seu convite para o Escala Fácil')
            ->greeting('Olá!')->line('A gerência convidou você para acessar o Escala Fácil.')
            ->line('Use o link para criar sua própria senha. Nenhuma senha provisória foi enviada.')
            ->action('Criar minha senha', rtrim(config('app.url'), '/').route('account.activate', ['token' => $this->token], false))
            ->line('O link é válido até '.AccountInvitation::findOrFail($this->invitationId)->expires_at->format('d/m/Y H:i').' ('.config('app.timezone').') e só pode ser usado uma vez. Um novo envio invalida o link anterior.')
            ->line('Se você não esperava este convite, não use o link e entre em contato com a gerência.');
    }
}
