<div>
    <p class="eyebrow">Nova credencial</p>
    <h1 class="auth-title">Crie uma nova senha</h1>
    <p class="auth-description">Defina e confirme sua senha para voltar ao Escala Fácil.</p>
    <x-auth.form action="resetPassword">
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input wire:model="email" id="email" type="email" name="email" autocomplete="username" inputmode="email" enterkeyhint="next" autocapitalize="none" spellcheck="false" class="field-input" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-auth.password-field id="password" label="Nova senha" wire:model="password" autocomplete="new-password" enterkeyhint="next" />
        <x-auth.password-field id="password_confirmation" label="Confirmar nova senha" wire:model="password_confirmation" autocomplete="new-password" enterkeyhint="go" />
        <x-auth.submit action="resetPassword" label="Salvar nova senha" busy="Salvando…" />
    </x-auth.form>
    <a href="{{ route('login') }}" class="auth-link mt-4 inline-flex" wire:navigate>Voltar para entrar</a>
</div>
