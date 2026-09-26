<div>
    <p class="eyebrow">Nova credencial</p>
    <h2 class="auth-title">Crie uma nova senha</h2>
    <p class="auth-description">Escolha uma senha segura para voltar ao Escala Fácil.</p>

    <form method="post" wire:submit="resetPassword" class="mt-8 space-y-5" novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" class="field-input" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
        </div>
        <div x-data="{ visible: false }">
            <label for="password" class="field-label">Nova senha</label>
            <div class="relative">
                <input wire:model="password" id="password" x-bind:type="visible ? 'text' : 'password'" autocomplete="new-password" class="field-input pr-20" @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                <button type="button" class="password-toggle" x-on:click="visible = ! visible" x-bind:aria-label="visible ? 'Ocultar senha' : 'Mostrar senha'" x-text="visible ? 'Ocultar' : 'Mostrar'"></button>
            </div>
            @error('password') <p id="password-error" class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password_confirmation" class="field-label">Confirmar nova senha</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" class="field-input">
        </div>
        <button type="submit" class="button-primary w-full" wire:loading.attr="disabled" wire:target="resetPassword">
            <span wire:loading.remove wire:target="resetPassword">Salvar nova senha</span>
            <span wire:loading wire:target="resetPassword">Salvando…</span>
        </button>
    </form>
</div>
