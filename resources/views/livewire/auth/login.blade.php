<div>
    <p class="eyebrow">Área restrita</p>
    <h1 class="auth-title">Entre na sua conta</h1>
    <p class="auth-description">Use o e-mail e a senha fornecidos pela gestão.</p>
    <x-auth-session-status :status="session('status')" class="mt-5" />
    <x-auth.form action="login" action-url="{{ route('login') }}">
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input wire:model="form.email" id="email" type="email" name="email" autocomplete="username" inputmode="email" enterkeyhint="next" autocapitalize="none" spellcheck="false" class="field-input" placeholder="nome@instituicao.com.br" @error('form.email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('form.email')<p id="email-error" class="field-error">{{ $message }}</p>@enderror
        </div>
        <x-auth.password-field id="password" label="Senha" error-key="form.password" wire:model="form.password" enterkeyhint="go" placeholder="Digite sua senha">
            <a href="{{ route('password.request') }}" class="auth-link" wire:navigate>Esqueci minha senha</a>
        </x-auth.password-field>
        <label class="auth-remember">
            <input wire:model="form.remember" type="checkbox" name="remember" class="size-4 shrink-0 rounded border-line text-primary focus:ring-focus">
            <span>Manter conectado neste dispositivo</span>
        </label>
        <x-auth.submit action="login" label="Entrar" busy="Verificando…" />
    </x-auth.form>
</div>
