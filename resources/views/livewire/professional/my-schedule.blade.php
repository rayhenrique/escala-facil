<div>
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Área profissional</p>
            <h1 class="page-title">Minha escala</h1>
            <p class="page-description">Consulte seus plantões, negocie trocas e informe ausências.</p>
        </div>
        <div class="w-full sm:w-auto"><label for="month" class="field-label">Mês exibido</label><input wire:model.live="month" id="month" type="month" class="field-input sm:w-48"></div>
    </div>

    <x-flash-notifications />

    @if ($swapReceipt)
        <x-swap-receipt :receipt="$swapReceipt" class="mt-6" />
    @endif

    @if ($swapLimitReached)
        <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-900" role="status">
            @if ($month === now()->format('Y-m'))
                Você atingiu a cota de quatro trocas aprovadas neste mês. Novas solicitações e aceites serão liberados no próximo mês.
            @else
                Você atingiu a cota de quatro trocas aprovadas no mês corrente ({{ now()->format('m/Y') }}). Novas solicitações e aceites serão liberados no próximo mês.
            @endif
        </div>
    @endif

    @if ($incomingSwaps->isNotEmpty())
        <section class="mt-8" aria-labelledby="incoming-title">
            <div class="flex items-end justify-between gap-4"><div><p class="eyebrow">Sua resposta</p><h2 id="incoming-title" class="mt-2 text-xl font-semibold">Pedidos para você cobrir</h2></div><span class="count-badge">{{ $incomingSwaps->count() }}</span></div>
            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                @foreach ($incomingSwaps as $swap)
                    <article class="panel" wire:key="incoming-swap-{{ $swap->id }}">
                        <x-status-badge :status="$swap->status" label="Aguardando você" />
                        <h3 class="mt-4 text-lg font-semibold">{{ $swap->shift->date->format('d/m/Y') }} · {{ $swap->shift->time_label }}</h3>
                        <p class="mt-2 text-sm text-muted">{{ $swap->shift->sector->name }} · {{ $swap->shift->category->name }}</p>
                        @if ($swap->substituteShift)
                            <p class="mt-2 text-xs font-semibold text-coverage">Contrapartida: você cederá seu plantão de {{ $swap->substituteShift->date->format('d/m/Y') }} ({{ $swap->substituteShift->time_label }}).</p>
                        @endif
                        <p class="mt-4 text-sm"><span class="text-muted">Solicitado por</span> <strong>{{ $swap->requester->name }}</strong></p>
                        <button type="button" wire:click="requestAcceptSwap({{ $swap->id }})" wire:loading.attr="disabled" class="button-primary mt-5 inline-flex w-full sm:w-auto" @disabled($swapLimitReached)>{{ $swapLimitReached ? 'Cota mensal atingida' : 'Aceitar e enviar à gerência' }}</button>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if ($swapShiftId)
        <section class="panel mt-8 border-primary" aria-labelledby="swap-form-title">
            <div class="section-heading"><div><p class="eyebrow">Solicitação de troca</p><h2 id="swap-form-title">Escolha um substituto elegível</h2></div><button type="button" wire:click="cancelAction" class="button-secondary w-full sm:w-auto">Cancelar</button></div>
            <form method="post" wire:submit="reviewSwapRequest" class="mt-6 max-w-xl" novalidate>
                @csrf
                <label for="substitute" class="field-label">Profissional substituto</label>
                <select wire:model.live="substituteId" id="substitute" class="field-input" @error('substitute_id') aria-invalid="true" aria-describedby="substitute-error" @enderror>
                    <option value="">Selecione</option>
                    @foreach ($this->eligibleSubstitutes as $professional)<option value="{{ $professional->id }}">{{ $professional->name }}</option>@endforeach
                </select>
                @error('substitute_id') <p id="substitute-error" class="field-error">{{ $message }}</p> @enderror

                @if ($substituteId && $this->eligibleSubstituteShifts->isNotEmpty())
                    <div class="mt-4">
                        <label for="substitute-shift" class="field-label">Plantão de contrapartida <span class="font-normal text-muted">(opcional — troca de dias)</span></label>
                        <select wire:model="substituteShiftId" id="substitute-shift" class="field-input" @error('substitute_shift_id') aria-invalid="true" aria-describedby="substitute-shift-error" @enderror>
                            <option value="">Apenas cobertura (sem plantão de volta)</option>
                            @foreach ($this->eligibleSubstituteShifts as $s)
                                <option value="{{ $s->id }}">{{ $s->date->format('d/m/Y') }} · {{ $s->time_label }} ({{ $s->sector->name }})</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs text-muted">Selecione para realizar uma troca mútua de dias com o colega.</p>
                        @error('substitute_shift_id') <p id="substitute-shift-error" class="field-error">{{ $message }}</p> @enderror
                    </div>
                @endif

                @error('shift_id') <p class="field-error">{{ $message }}</p> @enderror
                @if ($this->eligibleSubstitutes->isEmpty())<p class="mt-3 text-sm text-muted">Não há profissionais elegíveis e disponíveis na sua categoria e setor.</p>@endif
                <button type="submit" class="button-primary mt-5 inline-flex w-full sm:w-auto" @disabled($this->eligibleSubstitutes->isEmpty()) wire:loading.attr="disabled" wire:target="reviewSwapRequest">Revisar solicitação</button>
            </form>
        </section>
    @endif

    @if ($absenceShiftId)
        <section class="panel mt-8 border-amber-300" aria-labelledby="absence-form-title">
            <div class="section-heading"><div><p class="eyebrow">Ausência justificada</p><h2 id="absence-form-title">Informe o motivo da ausência</h2></div><button type="button" wire:click="cancelAction" class="button-secondary w-full sm:w-auto">Cancelar</button></div>
            <form method="post" wire:submit="reviewAbsence" class="mt-6 max-w-2xl space-y-5" novalidate>
                @csrf
                <div><label for="absence-reason" class="field-label">Justificativa</label><textarea wire:model="absenceReason" id="absence-reason" rows="5" class="field-input min-h-32 resize-none" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror></textarea>@error('reason') <p id="reason-error" class="field-error">{{ $message }}</p> @enderror</div>
                <div><label for="attachment" class="field-label">Documento comprobatório <span class="font-normal text-muted">(opcional)</span></label><input wire:model="attachment" id="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="file-input" @error('attachment') aria-invalid="true" aria-describedby="attachment-error" @enderror><p class="mt-2 text-xs text-muted">PDF, JPG ou PNG de até 5 MB.</p>@error('attachment') <p id="attachment-error" class="field-error">{{ $message }}</p> @enderror</div>
                @error('shift_id') <p class="field-error">{{ $message }}</p> @enderror
                <button type="submit" class="button-primary inline-flex w-full sm:w-auto" wire:loading.attr="disabled" wire:target="reviewAbsence,attachment"><span wire:loading.remove wire:target="reviewAbsence">Revisar ausência</span><span wire:loading wire:target="reviewAbsence,attachment">Validando…</span></button>
            </form>
        </section>
    @endif

    <section class="mt-10" aria-labelledby="schedule-title">
        <div><p class="eyebrow">Agenda mensal</p><h2 id="schedule-title" class="mt-2 text-xl font-semibold">Seus plantões</h2></div>
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            @forelse ($shifts as $shift)
                @php
                    $hasActiveSwap = $shift->swaps->contains(fn ($swap) => in_array($swap->status, [\App\Enums\ShiftSwapStatus::PendingProfessional, \App\Enums\ShiftSwapStatus::PendingManager, \App\Enums\ShiftSwapStatus::Approved], true));
                    $hasPendingSwap = $shift->swaps->contains(fn ($swap) => in_array($swap->status, [\App\Enums\ShiftSwapStatus::PendingProfessional, \App\Enums\ShiftSwapStatus::PendingManager], true));
                @endphp
                <article class="shift-card" wire:key="my-shift-{{ $shift->id }}">
                    <div class="shift-card-date"><span>{{ $shift->date->translatedFormat('D') }}</span><strong>{{ $shift->date->format('d') }}</strong><small>{{ $shift->date->translatedFormat('M') }}</small></div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-status-badge :status="$shift->status" />
                            @if (isset($swappedShiftsDetails[$shift->id]))
                                @php($swapInfo = $swappedShiftsDetails[$shift->id])
                                <div class="relative inline-flex items-center group">
                                    <span
                                        tabindex="0"
                                        role="note"
                                        aria-label="{{ $swapInfo['tooltip'] }}"
                                        title="{{ $swapInfo['tooltip'] }}"
                                        class="inline-flex items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-800 cursor-help transition-all hover:bg-amber-100 hover:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400"
                                    >
                                        <span aria-hidden="true">⇄</span> Troca realizada
                                    </span>

                                    <!-- Tooltip flutuante com detalhes da troca -->
                                    <div
                                        role="tooltip"
                                        class="pointer-events-none absolute bottom-full left-0 sm:left-1/2 sm:-translate-x-1/2 mb-2 hidden group-hover:flex group-focus-within:flex flex-col items-start sm:items-center z-50 transition-all duration-150 drop-shadow-xl"
                                    >
                                        <div class="w-64 max-w-xs rounded-xl bg-slate-900/95 backdrop-blur p-3 text-xs text-white shadow-2xl ring-1 ring-white/10 text-left">
                                            <div class="flex items-center justify-between gap-2 border-b border-slate-700/80 pb-1.5 mb-2 font-semibold">
                                                <span class="inline-flex items-center gap-1.5 text-amber-300">
                                                    <span aria-hidden="true" class="text-sm">⇄</span>
                                                    Troca realizada
                                                </span>
                                                @if ($swapInfo['is_mutual'])
                                                    <span class="rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-300">Mútua</span>
                                                @endif
                                            </div>
                                            <p class="font-medium text-slate-100 leading-snug">
                                                {{ $swapInfo['headline'] }}
                                            </p>
                                            <div class="mt-2 space-y-1 rounded-md bg-slate-800/80 p-2 text-[11px]">
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-slate-400">Solicitante:</span>
                                                    <span class="font-medium text-slate-100 truncate text-right">{{ $swapInfo['requester_name'] }}</span>
                                                </div>
                                                <div class="flex items-center justify-between gap-2">
                                                    <span class="text-slate-400">Substituto:</span>
                                                    <span class="font-medium text-slate-100 truncate text-right">{{ $swapInfo['substitute_name'] }}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="size-2 -mt-1 rotate-45 bg-slate-900/95 ring-1 ring-white/10 border-r border-b border-slate-700 sm:self-center ml-4 sm:ml-0"></div>
                                    </div>
                                </div>
                            @elseif ($hasPendingSwap)
                                <x-status-badge status="pending_professional" label="Troca em andamento" />
                            @endif
                        </div>
                        <h3 class="mt-3 text-lg font-semibold">{{ $shift->time_label }}</h3>
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
                <div class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" wire:key="my-swap-{{ $swap->id }}">
                    <div>
                        <p class="font-semibold">
                            {{ $swap->shift->date->format('d/m/Y') }} · 
                            @if ($swap->requester_id === auth()->id())
                                {{ $swap->substitute->name }}
                            @else
                                Solicitado por {{ $swap->requester->name }}
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-muted">Atualizado em {{ $swap->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <x-status-badge :status="$swap->status" />
                </div>
            @empty
                <p class="py-4 text-sm text-muted">Você ainda não possui solicitações de troca.</p>
            @endforelse
        </div>
    </section>

    <x-confirmation-modal
        :show="$pendingConfirmation !== null"
        id="professional-action"
        :title="match ($pendingConfirmation) {
            'request_swap' => 'Enviar solicitação de troca?',
            'accept_swap' => 'Aceitar cobertura deste plantão?',
            'absence' => 'Registrar ausência?',
            default => 'Confirmar ação?',
        }"
        close-action="cancelConfirmation"
        :tone="$pendingConfirmation === 'absence' ? 'warning' : 'primary'"
    >
        @if ($confirmationSummary)
            <p><strong>{{ $confirmationSummary['shift'] }}</strong></p>
            <p>{{ $confirmationSummary['location'] }}</p>
            @if ($pendingConfirmation === 'request_swap')
                <p>Substituto: <strong>{{ $confirmationSummary['substitute'] }}</strong></p>
                @if (! empty($confirmationSummary['counterpart']))
                    <p class="mt-2 font-semibold text-coverage">Contrapartida: você assumirá o plantão do colega em {{ $confirmationSummary['counterpart'] }}.</p>
                @endif
                <p>O profissional receberá uma notificação para aceitar ou recusar a troca.</p>
            @elseif ($pendingConfirmation === 'accept_swap')
                <p>Solicitado por: <strong>{{ $confirmationSummary['requester'] }}</strong></p>
                @if (! empty($confirmationSummary['counterpart']))
                    <p class="mt-2 font-semibold text-coverage">Contrapartida: você cederá seu plantão de {{ $confirmationSummary['counterpart'] }} ao solicitante.</p>
                @endif
                @if (! empty($confirmationSummary['double_shift']))
                    <p class="mt-2 font-semibold text-amber-700">Atenção: este aceite emenda (dobra) com seu plantão de {{ $confirmationSummary['double_shift'] }}. A gerência decidirá com essa informação.</p>
                @endif
                <p>Ao confirmar, o pedido seguirá para decisão da gerência.</p>
            @elseif ($pendingConfirmation === 'absence')
                <p>Justificativa: <strong>{{ $confirmationSummary['reason'] }}</strong></p>
                <p>O plantão ficará descoberto e exigirá realocação pela gerência.</p>
            @endif
        @endif
        <x-slot:actions>
            <button type="button" wire:click="cancelConfirmation" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Voltar e revisar</button>
            @if ($pendingConfirmation === 'request_swap')
                <button type="button" wire:click="confirmSwapRequest" wire:loading.attr="disabled" wire:target="confirmSwapRequest" class="button-primary w-full sm:w-auto">Confirmar envio</button>
            @elseif ($pendingConfirmation === 'accept_swap')
                <button type="button" wire:click="confirmAcceptSwap" wire:loading.attr="disabled" wire:target="confirmAcceptSwap" class="button-primary w-full sm:w-auto">Confirmar aceite</button>
            @elseif ($pendingConfirmation === 'absence')
                <button type="button" wire:click="confirmAbsence" wire:loading.attr="disabled" wire:target="confirmAbsence" class="button-danger w-full sm:w-auto">Confirmar ausência</button>
            @endif
        </x-slot:actions>
    </x-confirmation-modal>
</div>
