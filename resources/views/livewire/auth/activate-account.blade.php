<div>
    <p class="eyebrow">Convite da gerência</p>
    <h1 class="auth-title">Crie sua senha</h1>
    <p class="auth-description">Defina uma senha pessoal para ativar seu acesso ao Escala Fácil. Use o link recebido por e-mail.</p>
    <x-auth.form action="activate">
        @error('invitation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
        <x-auth.password-field id="password" label="Sua senha" wire:model="password" autocomplete="new-password" enterkeyhint="next" />
        <x-auth.password-field id="password_confirmation" label="Confirmar sua senha" wire:model="password_confirmation" autocomplete="new-password" enterkeyhint="go" />
        <p class="text-sm text-muted">Use pelo menos 8 caracteres. Se o convite não funcionar, peça um novo link à gerência.</p>
        <x-auth.submit action="activate" label="Ativar minha conta" busy="Ativando…" />
    </x-auth.form>
    <a href="{{ route('login') }}" class="auth-link mt-4 inline-flex" wire:navigate>Voltar para entrar</a>
</div>
