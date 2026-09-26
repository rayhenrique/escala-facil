<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Área profissional</p>
            <h1 class="page-title">Minha escala</h1>
            <p class="page-description">Consulte seus plantões, negocie trocas e informe ausências.</p>
        </div>
        <div class="w-full sm:w-auto"><label for="month" class="field-label">Mês exibido</label><input wire:model.live="month" id="month" type="month" class="field-input sm:w-48"></div>
    </div>

    @if (session('status'))
        <div class="status-success mt-6" role="status">{{ session('status') }}</div>
    @endif

    @if ($swapReceipt)
        <x-swap-receipt :receipt="$swapReceipt" class="mt-6" />
    @endif

    @if ($swapLimitReached)
        <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900" role="status">
            Você atingiu a cota de quatro trocas aprovadas neste mês. Novas solicitações e aceites serão liberados no próximo mês.
        </div>
    @endif

    @if ($incomingSwaps->isNotEmpty())
        <section class="mt-8" aria-labelledby="incoming-title">
            <div class="flex items-end justify-between gap-4"><div><p class="eyebrow">Sua resposta</p><h2 id="incoming-title" class="mt-2 text-xl font-semibold">Pedidos para você cobrir</h2></div><span class="count-badge">{{ $incomingSwaps->count() }}</span></div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($incomingSwaps as $swap)
                    <article class="panel" wire:key="incoming-swap-{{ $swap->id }}">
                        <x-status-badge :status="$swap->status" label="Aguardando você" />
                        <h3 class="mt-4 text-lg font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ substr($swap->shift->start_time, 0, 5) }}–{{ substr($swap->shift->end_time, 0, 5) }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>
                        <p class="mt-4 text-sm"><span class="text-muted">Solicitado por</span> <strong>{{ $swap->requester->name }}</strong></p>
                        <button type="button" wire:click="acceptSwap({{ $swap->id }})" wire:loading.attr="disabled" class="button-primary mt-5 inline-flex w-full sm:w-auto" @disabled($swapLimitReached)>{{ $swapLimitReached ? 'Cota mensal atingida' : 'Aceitar e enviar à gerência' }}</button>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($swapShiftId)
        <section class="panel mt-8 border-primary" aria-labelledby="swap-form-title">
            <div class="section-heading"><div><p class="eyebrow">Solicitação de troca</p><h2 id="swap-form-title">Escolha um substituto elegível</h2></div><button type="button" wire:click="cancelAction" class="button-secondary w-full sm:w-auto">Cancelar</button></div>
            <form method="post" wire:submit="requestSwap" class="mt-6 max-w-xl" novalidate>
                @csrf
                <label for="substitute" class="field-label">Profissional substituto</label>
                <select wire:model="substituteId" id="substitute" class="field-input" @error('substitute_id') aria-invalid="true" aria-describedby="substitute-error" @enderror>
                    <option value="">Selecione</option>
                    @foreach ($this->eligibleSubstitutes as $professional)<option value="{{ $professional->id }}">{{ $professional->name }}</option>@endforeach
                </select>
                @error('substitute_id') <p id="substitute-error" class="field-error">{{ $message }}</p> @enderror
                @error('shift_id') <p class="field-error">{{ $message }}</p> @enderror
                @if ($this->eligibleSubstitutes->isEmpty())<p class="mt-3 text-sm text-muted">Não há profissionais elegíveis e disponíveis na sua categoria e setor.</p>@endif
                <button type="submit" class="button-primary mt-5 inline-flex w-full sm:w-auto" @disabled($this->eligibleSubstitutes->isEmpty()) wire:loading.attr="disabled">Enviar solicitação</button>
            </form>
        </section>
    @endif

    @if ($absenceShiftId)
        <section class="panel mt-8 border-amber-300" aria-labelledby="absence-form-title">
            <div class="section-heading"><div><p class="eyebrow">Ausência justificada</p><h2 id="absence-form-title">Informe o motivo da ausência</h2></div><button type="button" wire:click="cancelAction" class="button-secondary w-full sm:w-auto">Cancelar</button></div>
            <form method="post" wire:submit="reportAbsence" class="mt-6 max-w-2xl space-y-5" novalidate>
                @csrf
                <div><label for="absence-reason" class="field-label">Justificativa</label><textarea wire:model="absenceReason" id="absence-reason" rows="5" class="field-input min-h-32 resize-none" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror></textarea>@error('reason') <p id="reason-error" class="field-error">{{ $message }}</p> @enderror</div>
                <div><label for="attachment" class="field-label">Documento comprobatório <span class="font-normal text-muted">(opcional)</span></label><input wire:model="attachment" id="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="file-input" @error('attachment') aria-invalid="true" aria-describedby="attachment-error" @enderror><p class="mt-2 text-xs text-muted">PDF, JPG ou PNG de até 5 MB.</p>@error('attachment') <p id="attachment-error" class="field-error">{{ $message }}</p> @enderror</div>
                @error('shift_id') <p class="field-error">{{ $message }}</p> @enderror
                <button type="submit" class="button-primary inline-flex w-full sm:w-auto" wire:loading.attr="disabled" wire:target="reportAbsence,attachment"><span wire:loading.remove wire:target="reportAbsence">Registrar ausência</span><span wire:loading wire:target="reportAbsence,attachment">Enviando…</span></button>
            </form>
        </section>
    @endif

    <section class="mt-10" aria-labelledby="schedule-title">
        <div><p class="eyebrow">Agenda mensal</p><h2 id="schedule-title" class="mt-2 text-xl font-semibold">Seus plantões</h2></div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse ($shifts as $shift)
                @php($hasActiveSwap = $shift->swaps->contains(fn ($swap) => in_array($swap->status, [\App\Enums\ShiftSwapStatus::PendingProfessional, \App\Enums\ShiftSwapStatus::PendingManager, \App\Enums\ShiftSwapStatus::Approved], true)))
                <article class="shift-card" wire:key="my-shift-{{ $shift->id }}">
                    <div class="shift-card-date"><span>{{ $shift->date->translatedFormat('D') }}</span><strong>{{ $shift->date->format('d') }}</strong><small>{{ $shift->date->translatedFormat('M') }}</small></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2"><x-status-badge :status="$shift->status" />@if($hasActiveSwap)<x-status-badge status="pending_professional" label="Troca em andamento" />@endif</div>
                        <h3 class="mt-3 text-lg font-semibold">{{ substr($shift->start_time, 0, 5) }}–{{ substr($shift->end_time, 0, 5) }}</h3>
                        <p class="mt-1 text-sm text-muted">{{ $shift->sector->name }} · {{ $shift->category->name }}</p>
                        @if ($shift->date->isAfter(today()) && $shift->status === \App\Enums\ShiftStatus::Scheduled)
                            <div class="mt-5 flex flex-wrap gap-2">
                                <button type="button" wire:click="openSwap({{ $shift->id }})" class="button-secondary" @disabled($hasActiveSwap || $swapLimitReached)>Solicitar troca</button>
                                <button type="button" wire:click="openAbsence({{ $shift->id }})" class="table-action min-h-10">Informar ausência</button>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state lg:col-span-2"><p class="font-semibold text-ink">Nenhum plantão neste mês.</p><p class="mt-2 text-sm">Altere o mês para consultar outros períodos.</p></div>
            @endforelse
        </div>
    </section>

    <section class="panel mt-10" aria-labelledby="my-swaps-title">
        <p class="eyebrow">Acompanhamento</p><h2 id="my-swaps-title" class="mt-2 text-xl font-semibold">Minhas solicitações de troca</h2>
        <div class="mt-5 divide-y divide-line">
            @forelse ($mySwaps as $swap)
                <div class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" wire:key="my-swap-{{ $swap->id }}"><div><p class="font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->substitute->name }}</p><p class="mt-1 text-sm text-muted">Atualizado em {{ $swap->updated_at->format('d/m/Y H:i') }}</p></div><x-status-badge :status="$swap->status" /></div>
            @empty
                <p class="py-4 text-sm text-muted">Você ainda não solicitou trocas.</p>
            @endforelse
        </div>
    </section>
</div>
