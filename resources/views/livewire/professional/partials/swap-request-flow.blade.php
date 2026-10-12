<x-bottom-sheet :show="$swapShiftId !== null" id="swap-request" title="Solicitar troca" eyebrow="Sua escala" close-action="cancelAction" max-width="2xl" :track-changes="true" initial-focus="#swap-form-title" return-focus="[data-swap-return]" x-on:swap-step-changed.window="$nextTick(() => { dirty = true; document.getElementById('swap-form-title')?.focus({ preventScroll: true }); $el.querySelector('[data-dialog-body]')?.scrollTo(0, 0); })">
    @if ($swapShiftId)
        <x-step-progress :steps="['Plantão', 'Tipo', 'Colega', 'Revisão', 'Envio']" :current="$swapStep" label="Solicitação de troca" class="!grid grid-cols-5 !gap-1 [&>li]:flex-col [&>li]:text-xs" />
        <h3 id="swap-form-title" tabindex="-1" class="mt-6 text-xl font-semibold text-ink">{{ match ($swapStep) { 1 => 'Escolha o plantão', 2 => 'Defina a modalidade', 3 => 'Escolha o colega', 4 => 'Revise os detalhes', default => 'Enviar solicitação de troca?' } }}</h3>
        <p class="mt-1 text-sm text-muted">Etapa {{ $swapStep }} de 5. A solicitação será enviada somente após sua confirmação.</p>
        <div class="mt-5 space-y-4" wire:key="swap-step-{{ $swapStep }}">
            @if ($swapStep === 1)
                <p class="text-sm text-muted">Somente seus plantões futuros, agendados e sem troca ativa estão disponíveis.</p>
                <fieldset id="choose-shifts"><legend class="field-label">Plantão a oferecer</legend>
                    @foreach ($this->selectableSwapShifts as $candidate)
                        <label class="flex min-h-11 items-start gap-3 rounded-lg border border-line p-3 my-2 cursor-pointer"><input type="radio" wire:model.live="chosenShiftId" value="{{ $candidate->id }}" name="chosen-shift" class="mt-1"><span><strong class="block">{{ $candidate->period()->datedLabel() }}</strong><span class="text-sm text-muted">{{ $candidate->sector?->name }} · {{ $candidate->category?->name }}</span></span></label>
                    @endforeach
                </fieldset>
                @if ($this->selectableSwapShifts->isEmpty())<x-empty-state title="Nenhum plantão disponível" description="Consulte sua escala e os pedidos ativos antes de tentar novamente." />@endif
                <x-operational-pagination :paginator="$this->selectableSwapShifts" label="Paginação dos plantões disponíveis" controls="choose-shifts" heading="swap-form-title" />
                @error('chosenShiftId')<p class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror
            @elseif ($swapStep === 2)
                <fieldset><legend class="field-label">Como deseja trocar?</legend>
                    <label class="flex min-h-11 gap-3 rounded-lg border border-line p-4 my-2"><input type="radio" wire:model.live="swapMode" value="simple" name="swap-mode" class="mt-1"><span><strong class="block">Troca simples</strong><span class="text-sm text-muted">O colega assume seu plantão, sem contrapartida.</span></span></label>
                    <label class="flex min-h-11 gap-3 rounded-lg border border-line p-4 my-2"><input type="radio" wire:model.live="swapMode" value="mutual" name="swap-mode" class="mt-1"><span><strong class="block">Troca mútua</strong><span class="text-sm text-muted">O colega assume seu plantão e você assume um plantão dele.</span></span></label>
                </fieldset>
                @error('swapMode')<p class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror
            @elseif ($swapStep === 3)
                <div><label for="professional-search" class="field-label">Pesquisar profissional</label><input id="professional-search" type="search" wire:model.live.debounce.300ms="professionalSearch" maxlength="100" class="field-input" placeholder="Nome do colega" autocomplete="off"></div>
                <p class="text-sm text-muted">Mesmo setor e categoria do plantão, com menos de quatro trocas aprovadas no mês. A disponibilidade será reavaliada no aceite e na aprovação.</p>
                <fieldset id="eligible-professionals" tabindex="-1" @error('substitute_id') aria-invalid="true" aria-describedby="substitute-error" @enderror><legend class="field-label">Profissional substituto</legend>
                    @foreach ($this->eligibleSubstitutes as $professional)
                        <label class="flex min-h-11 gap-3 items-center rounded-lg border border-line p-3 my-2"><input type="radio" wire:model.live="substituteId" value="{{ $professional->id }}" name="substitute"><span>{{ $professional->name }}</span></label>
                    @endforeach
                </fieldset>
                <p class="text-sm text-muted" role="status">{{ $this->eligibleSubstitutes->total() }} profissional(is) encontrado(s).</p>
                @if ($this->eligibleSubstitutes->isEmpty())<x-empty-state title="Nenhum profissional encontrado" description="Ajuste a pesquisa. Profissionais de outro setor ou categoria e aqueles que atingiram a cota não aparecem aqui." />@endif
                <x-operational-pagination :paginator="$this->eligibleSubstitutes" label="Paginação dos profissionais elegíveis" controls="eligible-professionals" heading="swap-form-title" />
                @error('substitute_id')<p id="substitute-error" class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror
                @if ($swapMode === 'mutual' && $substituteId)
                    <fieldset id="counterpart-shifts" tabindex="-1" @error('substitute_shift_id') aria-invalid="true" aria-describedby="counterpart-error" @enderror><legend class="field-label">Plantão que você assumirá</legend>
                        @foreach ($this->eligibleSubstituteShifts as $counterpart)
                            <label class="flex min-h-11 gap-3 rounded-lg border border-line p-3 my-2"><input type="radio" wire:model.live="substituteShiftId" value="{{ $counterpart->id }}" name="counterpart" class="mt-1"><span>{{ $counterpart->period()->datedLabel() }}</span></label>
                        @endforeach
                    </fieldset>
                    @if ($this->eligibleSubstituteShifts->isEmpty())<p class="field-error">Este colega não possui contrapartida disponível no mesmo setor e categoria. Escolha outro colega ou volte para troca simples.</p>@endif
                    <x-operational-pagination :paginator="$this->eligibleSubstituteShifts" label="Paginação das contrapartidas" controls="counterpart-shifts" heading="swap-form-title" />
                @endif
            @elseif ($confirmationSummary)
                <div class="rounded-lg border border-line p-4 space-y-2"><p class="eyebrow">{{ $confirmationSummary['counterpart'] ? 'Troca mútua' : 'Troca simples' }}</p><p><strong>{{ $confirmationSummary['substitute'] }}</strong> assumirá o plantão de <strong>{{ $confirmationSummary['requester'] }}</strong>.</p><p class="font-semibold">{{ $confirmationSummary['shift'] }}</p><p class="text-sm text-muted">{{ $confirmationSummary['location'] }}</p></div>
                @if ($confirmationSummary['counterpart'])<div class="rounded-lg border border-line p-4 space-y-2"><p><strong>{{ $confirmationSummary['requester'] }}</strong> assumirá o plantão de <strong>{{ $confirmationSummary['substitute'] }}</strong>.</p><p class="font-semibold">{{ $confirmationSummary['counterpart'] }}</p><p class="text-sm text-muted">{{ $confirmationSummary['counterpart_location'] }}</p></div>@endif
                <p class="text-sm text-muted">Cotas de {{ now()->format('m/Y') }}: você, {{ $confirmationSummary['quota'] }}; colega, {{ $confirmationSummary['substitute_quota'] }} trocas aprovadas. O envio não conta como aprovação.</p>
                @foreach ($confirmationSummary['warnings'] as $warning)<p class="rounded-lg border border-line p-3 text-sm text-ink" role="status">{{ $warning }}</p>@endforeach
                <p class="text-sm">O colega receberá uma notificação para aceitar ou recusar. Depois do aceite, a gerência decidirá. Os plantões permanecem atribuídos até a aprovação.</p>
            @endif
            @error('shift_id')<p class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror
            @error('substitute_shift_id')<p id="counterpart-error" class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror
            @if ($swapStep >= 4)@error('substitute_id')<p id="substitute-error" class="field-error" role="alert" tabindex="-1" data-validation-error>{{ $message }}</p>@enderror @endif
        </div>
    @endif
    <x-slot:actions>
        <x-action-button x-on:click="requestClose()" x-bind:disabled="busy">Cancelar</x-action-button>
        @if ($swapStep > 1)<x-action-button wire:click="previousSwapStep" target="previousSwapStep, nextSwapStep, confirmSwapRequest">Voltar</x-action-button>@endif
        @if ($swapStep < 5)<x-action-button wire:click="nextSwapStep" target="nextSwapStep, confirmSwapRequest" variant="primary" :class="$swapStep > 1 ? 'col-span-2 sm:col-span-1' : ''">Continuar</x-action-button>
        @else<x-action-button wire:click="confirmSwapRequest" target="confirmSwapRequest" variant="primary" class="col-span-2 sm:col-span-1" busy-label="Enviando…">Confirmar solicitação</x-action-button>@endif
    </x-slot:actions>
</x-bottom-sheet>
