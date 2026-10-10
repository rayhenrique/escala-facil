<div>
    <a href="{{ route('login') }}" class="auth-link inline-flex items-center gap-2" wire:navigate>← Voltar para entrar</a>
    <p class="eyebrow mt-5">Recuperação de acesso</p>
    <h1 class="auth-title">Redefina sua senha</h1>
    <p class="auth-description">Informe seu e-mail institucional para receber as instruções de recuperação.</p>
    <x-auth-session-status :status="session('status')" class="mt-5" />
    <x-auth.form action="sendResetLink">
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input wire:model="email" id="email" type="email" name="email" autocomplete="email" inputmode="email" enterkeyhint="send" autocapitalize="none" spellcheck="false" class="field-input" placeholder="nome@instituicao.com.br" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-auth.submit action="sendResetLink" label="Enviar instruções" busy="Enviando…" />
    </x-auth.form>
</div>
