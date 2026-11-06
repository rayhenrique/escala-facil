<div>
    <h1 class="page-title" tabindex="-1">Meu Perfil</h1>
    <p class="page-description">Consulte seus dados e atualize seu nome ou sua senha.</p>

    <x-flash-notifications />

    @if ($account->role === \App\Enums\UserRole::Professional)
        <section class="panel mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" aria-labelledby="profile-schedule-title">
            <div>
                <h2 id="profile-schedule-title" class="text-lg font-semibold">Minha escala</h2>
                <p class="mt-1 text-sm text-muted">Consulte seus plantões, horários e a situação de cada turno.</p>
            </div>
            <a href="{{ route('professional.dashboard', ['month' => now()->format('Y-m')]) }}#schedule-title" wire:navigate class="button-primary shrink-0 justify-center">Ver minha escala</a>
        </section>
    @endif

    <div class="mt-6 grid items-start gap-6 xl:grid-cols-2">
        <section class="panel min-w-0" aria-labelledby="profile-details-title">
            <h2 id="profile-details-title" class="text-lg font-semibold">Dados da conta</h2>
            <form method="post" wire:submit="saveName" novalidate class="mt-4 space-y-4">
                @csrf
                <div>
                    <label for="profile-name" class="field-label">Nome completo</label>
                    <input id="profile-name" wire:model="name" type="text" autocomplete="name" maxlength="255" required class="field-input" @error('name') aria-invalid="true" aria-describedby="profile-name-error" @enderror>
                    @error('name')<p id="profile-name-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="saveName">
                    <span wire:loading.remove wire:target="saveName">Salvar nome</span>
                    <span wire:loading wire:target="saveName">Salvando…</span>
                </button>
            </form>
            <dl class="mt-5 grid gap-4 border-t border-line pt-5 text-sm sm:grid-cols-2">
                <div class="min-w-0 sm:col-span-2"><dt class="text-muted">E-mail cadastrado</dt><dd class="break-all font-medium">{{ $account->email }}</dd></div>
                <div><dt class="text-muted">Perfil de acesso</dt><dd class="break-words font-medium">{{ $account->role->label() }}</dd></div>
                <div><dt class="text-muted">Situação da conta</dt><dd class="font-medium">{{ $account->requires_activation ? 'Ativação pendente' : 'Ativa' }}</dd></div>
                <div><dt class="text-muted">Setor</dt><dd class="break-words font-medium">{{ $account->sector?->name ?? 'Sem vínculo' }}</dd></div>
                <div><dt class="text-muted">Categoria profissional</dt><dd class="break-words font-medium">{{ $account->category?->name ?? 'Sem vínculo' }}</dd></div>
            </dl>
        </section>

        <section class="panel min-w-0" aria-labelledby="profile-security-title">
            <h2 id="profile-security-title" class="text-lg font-semibold">Segurança</h2>
            <form method="post" x-data @profile-password-changed.window="$el.reset()"
                wire:submit="changePassword({
                    current_password: $event.target.elements.current_password.value,
                    password: $event.target.elements.password.value,
                    password_confirmation: $event.target.elements.password_confirmation.value
                })" novalidate class="mt-4 space-y-4">
                @csrf
                <x-auth.password-field id="current_password" label="Senha atual" required />
                <x-auth.password-field id="password" label="Nova senha" autocomplete="new-password" required />
                <x-auth.password-field id="password_confirmation" label="Confirmação da nova senha" autocomplete="new-password" required />
                <button type="submit" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="changePassword">
                    <span wire:loading.remove wire:target="changePassword">Alterar senha</span>
                    <span wire:loading wire:target="changePassword">Alterando…</span>
                </button>
            </form>
        </section>
    </div>
</div>
