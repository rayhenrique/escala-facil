<div>
    <p class="eyebrow">Área restrita</p>
    <h2 class="auth-title">Entre na sua conta</h2>
    <p class="auth-description">Use o e-mail e a senha fornecidos pela gestão.</p>

    <x-flash-notifications />

    <form method="post" action="{{ route('login') }}" wire:submit="login" class="mt-8 space-y-5" novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input
                wire:model="form.email"
                id="email"
                type="email"
                name="email"
                autocomplete="username"
                autofocus
                class="field-input"
                placeholder="nome@instituicao.com.br"
                @error('form.email') aria-invalid="true" aria-describedby="email-error" @enderror
            >
            @error('form.email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
        </div>

        <div x-data="{ visible: false }">
            <div class="flex items-center justify-between gap-4">
                <label for="password" class="field-label">Senha</label>
                <a href="{{ route('password.request') }}" class="text-link" wire:navigate>Esqueci minha senha</a>
            </div>
            <div class="relative">
                <input
                    wire:model="form.password"
                    id="password"
                    x-bind:type="visible ? 'text' : 'password'"
                    name="password"
                    autocomplete="current-password"
                    class="field-input pr-20"
                    placeholder="Digite sua senha"
                    @error('form.password') aria-invalid="true" aria-describedby="password-error" @enderror
                >
                <button type="button" class="password-toggle" x-on:click="visible = ! visible" x-bind:aria-label="visible ? 'Ocultar senha' : 'Mostrar senha'" x-text="visible ? 'Ocultar' : 'Mostrar'"></button>
            </div>
            @error('form.password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex w-fit items-center gap-3 text-sm text-muted">
            <input wire:model="form.remember" type="checkbox" name="remember" class="size-4 rounded border-line text-primary focus:ring-focus">
            Manter conectado neste dispositivo
        </label>

        <button type="submit" class="button-primary w-full" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Entrar</span>
            <span wire:loading wire:target="login">Verificando…</span>
        </button>
    </form>
</div>
