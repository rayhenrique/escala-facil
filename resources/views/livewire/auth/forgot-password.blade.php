<div>
    <a href="{{ route('login') }}" class="text-link inline-flex items-center gap-2" wire:navigate aria-label="Voltar para o login">← Voltar</a>
    <p class="eyebrow mt-8">Recuperação de acesso</p>
    <h2 class="auth-title">Redefina sua senha</h2>
    <p class="auth-description">Informe seu e-mail institucional. Enviaremos as instruções para criar uma nova senha.</p>

    @if (session('status'))
        <div class="status-success mt-6" role="status">{{ session('status') }}</div>
    @endif

    <form method="post" wire:submit="sendResetLink" class="mt-8 space-y-6" novalidate>
        @csrf
        <div>
            <label for="email" class="field-label">E-mail</label>
            <input wire:model="email" id="email" type="email" autocomplete="email" autofocus class="field-input" placeholder="nome@instituicao.com.br" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            @error('email') <p id="email-error" class="field-error">{{ $message }}</p> @enderror
        </div>
        <button type="submit" class="button-primary w-full" wire:loading.attr="disabled" wire:target="sendResetLink">
            <span wire:loading.remove wire:target="sendResetLink">Enviar instruções</span>
            <span wire:loading wire:target="sendResetLink">Enviando…</span>
        </button>
    </form>
</div>
