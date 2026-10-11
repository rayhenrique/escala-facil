@props(['user', 'manageable' => false])
@if ($user->invitation)
    <div class="mt-3 space-y-2 text-sm">
        <p class="font-semibold">Convite: {{ $user->invitation->status($user) }}</p>
        @if (! $user->invitation->accepted_at)
            <p class="text-xs text-muted">Validade: {{ $user->invitation->expires_at->format('d/m/Y H:i') }}</p>
            @if ($manageable && $user->requires_activation && $user->role === \App\Enums\UserRole::Professional)
                <div class="flex flex-wrap gap-2">
                    <button type="button" wire:click="resendInvitation({{ $user->id }})" class="table-action min-h-10" wire:loading.attr="disabled" wire:target="resendInvitation">Reenviar convite</button>
                    @if ($user->invitation->status($user) !== 'Revogado')<button type="button" wire:click="requestInvitationRevocation({{ $user->id }})" class="table-action min-h-10 text-danger">Revogar convite</button>@endif
                </div>
            @endif
        @endif
    </div>
@endif
