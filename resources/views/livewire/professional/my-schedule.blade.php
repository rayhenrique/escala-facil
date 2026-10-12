<div x-on:incoming-response-finished.window="$nextTick(() => { const heading = document.getElementById(window.innerWidth >= 1024 ? 'incoming-desktop-title' : 'incoming-title') ?? document.getElementById('professional-home'); heading?.focus({ preventScroll: true }); })" class="professional-home-layout" x-data="{ swapOpener: '', responseOpener: '' }" x-on:professional-form-opened.window="$nextTick(() => { const target = document.getElementById($event.detail.target); target?.focus(); if ($event.detail.target !== 'swap-form-title') target?.scrollIntoView({ block: 'start' }); })">
    <div class="flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="eyebrow">Área profissional</p>
            <h1 id="professional-home" tabindex="-1" class="page-title">Minha escala</h1>
            <p class="page-description">Consulte seus plantões, negocie trocas e informe ausências.</p>
        </div>
        <div class="w-full sm:w-auto"><label for="month" class="field-label">Mês exibido</label><input wire:model.live="month" id="month" type="month" class="field-input sm:w-48"></div>
    </div>

    <x-flash-notifications />
    <section class="professional-next panel mt-6 lg:hidden" aria-labelledby="next-shift-title">
        <p class="eyebrow">Sua próxima atividade</p>
        <h2 id="next-shift-title" class="mt-2 text-xl font-semibold">Próximo plantão</h2>
        <p class="mt-1 text-xs text-muted">A partir de agora, independentemente dos filtros abaixo.</p>
        @if ($nextShift)
            @php
                $period = $nextShift->period();
            @endphp
            <p class="mt-4 text-lg font-semibold">{{ $nextShift->date->translatedFormat('l, d/m/Y') }}</p>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div><dt class="text-muted">Início</dt><dd class="font-semibold">{{ $period->start->format('H:i') }}</dd></div>
                <div><dt class="text-muted">Término</dt><dd class="font-semibold">{{ $period->endLabel() }}</dd></div>
                <div><dt class="text-muted">Duração</dt><dd class="font-semibold">{{ $period->durationLabel() }}</dd></div>
                <div><dt class="text-muted">Setor</dt><dd class="break-words font-semibold">{{ $nextShift->sector?->name ?? 'Setor não informado' }}</dd></div>
                <div class="col-span-2"><dt class="text-muted">Categoria</dt><dd class="font-semibold">{{ $nextShift->category?->name ?? 'Categoria não informada' }}</dd></div>
            </dl>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-action-button id="professional-next-details" wire:click="showShiftDetails({{ $nextShift->id }})" variant="secondary" target="showShiftDetails">Ver detalhes</x-action-button>
                @if ($nextSwapReason === null)
                    <x-action-button variant="primary" wire:click="openSwap({{ $nextShift->id }})" target="openSwap" id="next-swap-open" x-on:click="swapOpener = 'next-swap-open'" x-bind:data-swap-return="swapOpener === 'next-swap-open' ? '' : null">Solicitar troca</x-action-button>
                @endif
                <a href="#schedule-title" class="button-secondary">Ver escala</a>
            </div>
            @if ($nextSwapReason)<p class="mt-3 text-sm text-muted">{{ $nextSwapReason }}</p>@endif
        @else
            <p class="mt-4 font-semibold">Nenhum próximo plantão agendado.</p>
            <p class="mt-1 text-sm text-muted">Consulte a escala mensal para ver plantões anteriores ou outras situações.</p>
            <a href="#schedule-title" class="button-secondary mt-4 inline-flex">Ver escala</a>
        @endif
        @if ($pendingResponses)
            <p class="mt-4 font-semibold">{{ $pendingResponses }} pedido(s) aguardando sua resposta.</p>
            @if ($pendingResponses !== $incomingSwaps->total())
                <p class="mt-1 text-sm text-muted">Há pedidos fora dos filtros atuais.</p>
                <x-action-button variant="secondary" wire:click="clearFilters" class="mt-2">Limpar filtros para ver pedidos</x-action-button>
            @else
                <a href="#incoming-title" class="mt-1 inline-flex min-h-11 items-center font-semibold text-primary">Ver pedidos recebidos</a>
            @endif
        @endif
        @if ($latestAbsence)
            <p class="mt-4 border-t border-line pt-3 text-sm text-muted">Última ausência informada: {{ $latestAbsence->shift?->date?->format('d/m/Y') ?? 'Plantão indisponível' }}. <x-status-badge :status="$latestAbsence->status" /></p>
        @endif
    </section>


    <x-professional.incoming-swaps :incoming-swaps="$incomingSwaps" :swap-limit-reached="$swapLimitReached" heading-id="incoming-title" class="lg:hidden" />
    <section class="mt-6 lg:hidden" aria-labelledby="sent-overview-title">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="sent-overview-title" class="text-lg font-semibold">Solicitações enviadas</h2>
            <a href="#my-swaps-title" class="inline-flex min-h-11 items-center font-semibold text-primary">Ver todas</a>
        </div>
        @forelse ($sentSwaps as $sent)
            <article class="mt-3 rounded-lg border border-line bg-surface p-4" wire:key="sent-overview-{{ $sent->id }}">
                <div class="flex flex-wrap items-center gap-2"><span class="text-xs font-semibold">{{ sprintf('TROCA-%06d', $sent->id) }}</span><x-status-badge :status="$sent->status" /></div>
                <p class="mt-2 text-sm font-semibold">{{ $sent->shift->period()->datedLabel() }}</p>
                <p class="mt-1 text-sm text-muted">Para {{ $sent->substitute?->name ?? 'Profissional indisponível' }} · {{ $sent->shift->sector?->name ?? 'Setor não informado' }}</p>
                @if ($sent->substituteShift)<p class="mt-1 text-sm text-muted">Contrapartida: {{ $sent->substituteShift->period()->datedLabel() }}</p>@endif
            </article>
        @empty
            <p class="mt-2 text-sm text-muted">Nenhuma solicitação enviada aguardando resposta ou decisão.</p>
        @endforelse
        @if ($sentSwaps->isNotEmpty())<p class="mt-2 text-xs text-muted">Até três pedidos recentes em andamento. O histórico completo está em suas solicitações.</p>@endif
    </section>
    <div class="professional-filters">
    <x-operational-filters kind="personal" />
    <p class="mt-3 text-sm text-muted" role="status">{{ $shifts->total() }} plantão(ões) e {{ $mySwaps->total() }} pedido(s) de troca encontrado(s).</p>
    </div>

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

    <x-professional.incoming-swaps :incoming-swaps="$incomingSwaps" :swap-limit-reached="$swapLimitReached" heading-id="incoming-desktop-title" class="hidden lg:block" />

    @include('livewire.professional.partials.swap-request-flow')

    @if ($absenceShiftId)
        <section class="panel mt-8 border-amber-300" aria-labelledby="absence-form-title">
            <div class="section-heading"><div><p class="eyebrow">Ausência justificada</p><h2 id="absence-form-title" tabindex="-1">Informe o motivo da ausência</h2></div><button type="button" wire:click="cancelAction" class="button-secondary w-full sm:w-auto">Cancelar</button></div>
            <form method="post" wire:submit="reviewAbsence" class="mt-6 max-w-2xl space-y-5" novalidate>
                @csrf
                <div><label for="absence-reason" class="field-label">Justificativa</label><textarea wire:model="absenceReason" id="absence-reason" rows="5" class="field-input min-h-32 resize-none" @error('reason') aria-invalid="true" aria-describedby="reason-error" @enderror></textarea>@error('reason') <p id="reason-error" class="field-error">{{ $message }}</p> @enderror</div>
                <div><label for="attachment" class="field-label">Documento comprobatório <span class="font-normal text-muted">(opcional)</span></label><input wire:model="attachment" id="attachment" type="file" accept=".pdf,.jpg,.jpeg,.png" class="file-input" @error('attachment') aria-invalid="true" aria-describedby="attachment-error" @enderror><p class="mt-2 text-xs text-muted">PDF, JPG ou PNG de até 5 MB.</p>@error('attachment') <p id="attachment-error" class="field-error">{{ $message }}</p> @enderror</div>
                @error('shift_id') <p class="field-error">{{ $message }}</p> @enderror
                <button type="submit" class="button-primary inline-flex w-full sm:w-auto" wire:loading.attr="disabled" wire:target="reviewAbsence,attachment"><span wire:loading.remove wire:target="reviewAbsence">Revisar ausência</span><span wire:loading wire:target="reviewAbsence,attachment">Validando…</span></button>
            </form>
        </section>
    @endif



    <section class="professional-schedule mt-10" aria-labelledby="schedule-title">
        <div><p class="eyebrow">Agenda mensal</p><h2 id="schedule-title" tabindex="-1" class="mt-2 text-xl font-semibold">Seus plantões</h2></div>
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
                        <p class="mt-1 text-sm text-muted">{{ $shift->sector?->name ?? 'Setor não informado' }} · {{ $shift->category?->name ?? 'Categoria não informada' }}</p>
                        @if ($shift->date->isAfter(today()) && $shift->status === \App\Enums\ShiftStatus::Scheduled)
                            <div class="mt-5 flex flex-wrap gap-2">
                                <button type="button" wire:click="openSwap({{ $shift->id }})" id="swap-open-{{ $shift->id }}" x-on:click="swapOpener = 'swap-open-{{ $shift->id }}'" x-bind:data-swap-return="swapOpener === 'swap-open-{{ $shift->id }}' ? '' : null" class="button-secondary" @disabled($hasActiveSwap || $swapLimitReached)>Solicitar troca</button>
                                <button type="button" wire:click="openAbsence({{ $shift->id }})" class="table-action min-h-10">Informar ausência</button>
                            </div>
                            @if ($hasActiveSwap || $swapLimitReached)
                                <p class="mt-2 text-sm text-muted">{{ $swapLimitReached ? 'Cota mensal de quatro trocas atingida.' : 'Este plantão já possui uma troca em andamento ou aprovada.' }}</p>
                            @endif
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state lg:col-span-2"><p class="font-semibold text-ink">Nenhum plantão correspondente aos filtros neste mês.</p><p class="mt-2 text-sm">Altere o mês, ajuste a pesquisa ou limpe os filtros.</p></div>
            @endforelse
        </div>
        <div class="mt-4">{{ $shifts->links() }}</div>
    </section>

    <section class="professional-swaps panel mt-6 lg:mt-10" aria-labelledby="my-swaps-title">
        <p class="eyebrow">Acompanhamento</p><h2 id="my-swaps-title" tabindex="-1" class="mt-2 text-xl font-semibold">Minhas solicitações de troca</h2>
        <div class="mt-5 divide-y divide-line">
            <div class="mb-4">
                <label for="personal-swap-status" class="field-label">Situação das minhas trocas</label>
                <select id="personal-swap-status" wire:model.live="swapStatusFilter" class="field-input sm:max-w-sm" @error('swapStatusFilter') aria-invalid="true" aria-describedby="personal-swap-status-error" @enderror>
                    <option value="">Todas as situações</option>
                    @foreach (\App\Enums\ShiftSwapStatus::cases() as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                </select>
            </div>
            @error('swapStatusFilter')<p id="personal-swap-status-error" class="field-error">{{ $message }}</p>@enderror
            @forelse ($mySwaps as $swap)
                <div class="flex flex-col gap-2 py-4 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between" wire:key="my-swap-{{ $swap->id }}">
                    <div>
                        <p class="font-semibold">
                            <span class="lg:hidden">{{ $swap->requester_id === auth()->id() ? 'Enviada' : 'Recebida' }} · {{ sprintf('TROCA-%06d', $swap->id) }}<br></span>
                            {{ $swap->shift->date->format('d/m/Y') }} · 
                            @if ($swap->requester_id === auth()->id())
                                {{ $swap->substitute?->name ?? 'Profissional indisponível' }}
                            @else
                                Solicitado por {{ $swap->requester?->name ?? 'Profissional indisponível' }}
                            @endif
                        </p>
                        <div class="mt-1 text-sm text-muted lg:hidden">
                            <p>{{ $swap->shift->time_label }} · {{ $swap->shift->sector?->name ?? 'Setor não informado' }} · {{ $swap->shift->category?->name ?? 'Categoria não informada' }}</p>
                            @if ($swap->substituteShift)<p class="mt-1">Contrapartida: {{ $swap->substituteShift->period()->datedLabel() }}</p>@endif
                        </div>
                        <p class="mt-1 text-sm text-muted">Atualizado em {{ $swap->updated_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <x-status-badge :status="$swap->status" />
                </div>
            @empty
                <p class="py-4 text-sm text-muted">Nenhuma solicitação corresponde aos filtros. Ajuste a pesquisa ou limpe os filtros.</p>
            @endforelse
        </div>
        @if ($incomingSwaps->total())
            <a href="#incoming-title" class="lg:hidden mt-3 inline-flex min-h-11 items-center font-semibold text-primary">Ver {{ $incomingSwaps->total() }} pedido(s) aguardando sua resposta</a>
        @endif
        @if ($incomingSwaps->total())
            <a href="#incoming-desktop-title" class="mt-3 hidden min-h-11 items-center font-semibold text-primary lg:inline-flex">Ver {{ $incomingSwaps->total() }} pedido(s) aguardando sua resposta</a>
        @endif
        <div class="mt-4">{{ $mySwaps->links() }}</div>
    </section>

    <x-bottom-sheet :show="$detailShiftId !== null" id="professional-shift-details" title="Detalhes do seu plantão" close-action="closeShiftDetails" return-focus="#professional-next-details">
        @if ($this->detailShift)
            <p class="font-semibold">{{ $this->detailShift->period()->datedLabel() }}</p>
            <p class="mt-2">{{ $this->detailShift->sector?->name ?? 'Setor não informado' }} · {{ $this->detailShift->category?->name ?? 'Categoria não informada' }}</p>
            <div class="mt-3"><x-status-badge :status="$this->detailShift->status" /></div>
        @endif
        <x-slot:actions><x-action-button variant="secondary" wire:click="closeShiftDetails">Fechar</x-action-button></x-slot:actions>
    </x-bottom-sheet>
    @include('livewire.professional.partials.incoming-response')
    <x-confirmation-modal
        :show="$pendingConfirmation !== null && $pendingConfirmation !== 'request_swap'"
        id="professional-action"
        :title="match ($pendingConfirmation) {
            'accept_swap' => 'Aceitar cobertura deste plantão?',
            'decline_swap' => 'Recusar este pedido?',
            'absence' => 'Registrar ausência?',
            default => 'Confirmar ação?',
        }"
        close-action="cancelConfirmation"
        :request-error-message="in_array($pendingConfirmation, ['accept_swap', 'decline_swap']) ? 'Não foi possível confirmar a resposta. Atualize os detalhes para conferir a situação antes de tentar novamente.' : 'Não foi possível concluir a solicitação. Confira os dados e tente novamente.'"
        :tone="in_array($pendingConfirmation, ['absence', 'decline_swap']) ? 'warning' : 'primary'"
    >
        @if ($confirmationSummary)
            <p><strong>{{ $confirmationSummary['shift'] }}</strong></p>
            <p>{{ $confirmationSummary['location'] }}</p>
            @if (in_array($pendingConfirmation, ['accept_swap', 'decline_swap']))
                <p>{{ $confirmationSummary['reference'] }} · Solicitante: <strong>{{ $confirmationSummary['requester'] }}</strong></p>
                @if ($confirmationSummary['counterpart'])<p>Seu plantão de contrapartida: <strong>{{ $confirmationSummary['counterpart'] }}</strong> · {{ $confirmationSummary['counterpart_location'] }}</p>@endif
                @foreach ($confirmationSummary['warnings'] as $warning)<p class="mt-2 rounded-lg border border-line p-3 text-sm" role="status">{{ $warning }}</p>@endforeach
                @if ($pendingConfirmation === 'accept_swap')
                    @foreach ($confirmationSummary['blocks'] as $block)<p class="field-error" role="alert">{{ $block }}</p>@endforeach
                    <p>Ao confirmar, o pedido seguirá para decisão da gerência. Nenhum plantão será transferido agora.</p>
                @else
                    @if (! $confirmationSummary['can_decline'])<p class="field-error" role="alert">{{ $confirmationSummary['blocks'][0] ?? 'Este pedido já não aguarda sua resposta. Atualize os detalhes.' }}</p>@endif
                    <p>Ao recusar, este pedido será encerrado e os profissionais serão notificados. Os plantões e as cotas permanecem sem alterações.</p>
                    <label for="decline-reason" class="field-label mt-4">Motivo da recusa <span class="font-normal text-muted">(opcional)</span></label>
                    <textarea wire:model="declineReason" id="decline-reason" rows="3" maxlength="1000" class="field-input" @error('declineReason') aria-invalid="true" aria-describedby="decline-reason-error" @enderror></textarea>
                    @error('declineReason')<p id="decline-reason-error" class="field-error">{{ $message }}</p>@enderror
                @endif
                @error('response')<p class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror
                <x-action-button wire:click="refreshIncomingDetails" target="refreshIncomingDetails, confirmAcceptSwap, confirmDeclineSwap" class="mt-3">Atualizar detalhes</x-action-button>
            @elseif ($pendingConfirmation === 'absence')
                <p>Justificativa: <strong>{{ $confirmationSummary['reason'] }}</strong></p>
                <p>O plantão ficará descoberto e exigirá realocação pela gerência.</p>
            @endif
        @endif
        <x-slot:actions>
            <button type="button" wire:click="cancelConfirmation" x-bind:disabled="busy" class="button-secondary w-full sm:w-auto" data-confirm-cancel>Voltar e revisar</button>
            @if ($pendingConfirmation === 'accept_swap')
                <button type="button" wire:click="confirmAcceptSwap" wire:loading.attr="disabled" wire:target="confirmAcceptSwap" class="button-primary w-full sm:w-auto" @disabled(! ($confirmationSummary['can_accept'] ?? false))>Confirmar aceite</button>
            @elseif ($pendingConfirmation === 'decline_swap')
                <x-action-button wire:click="confirmDeclineSwap" target="confirmDeclineSwap" variant="danger" :disabled="! ($confirmationSummary['can_decline'] ?? false)">Confirmar recusa</x-action-button>
            @elseif ($pendingConfirmation === 'absence')
                <button type="button" wire:click="confirmAbsence" wire:loading.attr="disabled" wire:target="confirmAbsence" class="button-danger w-full sm:w-auto">Confirmar ausência</button>
            @endif
        </x-slot:actions>
    </x-confirmation-modal>
</div>
