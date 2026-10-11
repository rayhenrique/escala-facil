<x-confirmation-modal :show="$reallocationShiftId !== null" id="reallocate-shift" :title="$reallocationPreview ? 'Confirmar realocação?' : 'Preencher plantão #'.$reallocationShiftId" :eyebrow="$reallocationPreview ? 'Confirmação necessária' : 'Realocação assistida'" close-action="closeReallocation" tone="primary" busy-action="confirmReallocation" trap-focus>
    @if ($reallocationSummary)
        <div class="space-y-4 text-left" data-reallocation-analysis>
            <div class="rounded-lg border border-line bg-canvas p-3">
                <p class="meta-label">Plantão descoberto #{{ $reallocationShiftId }}</p>
                <p class="mt-1 font-semibold text-ink">{{ $reallocationSummary['period'] }}</p>
                <p class="mt-1 text-sm text-muted">{{ $reallocationSummary['location'] }}</p>
            </div>
            @error('reallocation')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            @error('reallocationUserId')<p id="reallocation-user-error" class="field-error" role="alert">Bloqueio obrigatório: {{ $message }}</p>@enderror
            <p class="field-error" role="alert" tabindex="-1" hidden data-decision-request-error></p>
            @if ($reallocationPreview)
                <div class="rounded-lg border border-line p-3 text-sm">
                    <p class="meta-label">Prévia da alteração</p>
                    <p class="mt-2">Profissional: <strong>{{ $reallocationSummary['previous'] }}</strong> → <strong>{{ $reallocationPreview['name'] }}</strong>.</p>
                    <p class="mt-1">Status: <strong>Descoberto</strong> → <strong>Agendado</strong>.</p>
                    <p class="mt-2 text-muted">Data, horários, setor e categoria permanecem os indicados acima.</p>
                </div>
                <x-shift-assignment-alerts :blocks="$reallocationPreview['blocks']" :warnings="$reallocationPreview['warnings']" />
                @if (! $reallocationPreview['doubles'])<p class="text-sm text-muted">Nenhum conflito de horário ou dobra identificado na prévia.</p>@endif
                <p class="text-sm">Ao confirmar, o profissional será atribuído ao plantão. A alteração, o responsável e a data serão registrados no histórico.</p>
            @else
                <p class="text-sm text-muted">Profissionais cadastrados no mesmo setor e categoria, com perfil Profissional e sem sobreposição de horários. Plantões consecutivos aparecem com alerta de dobra.</p>
                <p class="text-sm text-muted">Bloqueios obrigatórios: incompatibilidade de cadastro e sobreposição de horários impedem a realocação. Consulte os horários ou escolha outro profissional.</p>
                @if ($reallocationSummary['unavailable'])<p class="text-sm">{{ $reallocationSummary['unavailable'] }} {{ $reallocationSummary['unavailable'] === 1 ? 'profissional compatível não está disponível' : 'profissionais compatíveis não estão disponíveis' }} por conflito de horários.</p>@endif
                <fieldset aria-describedby="reallocation-help{{ $errors->has('reallocationUserId') ? ' reallocation-user-error' : '' }}">
                    <legend class="field-label">Profissional para o plantão</legend>
                    <div class="mt-3 space-y-3">
                        @forelse ($reallocationCandidates as $candidate)
                            <label class="record-card flex cursor-pointer items-start gap-3" wire:key="candidate-{{ $candidate['id'] }}">
                                <input type="radio" name="reallocation-professional" wire:model="reallocationUserId" value="{{ $candidate['id'] }}" class="mt-1" @error('reallocationUserId') aria-invalid="true" @enderror>
                                <span class="min-w-0 text-sm"><strong class="block">{{ $candidate['name'] }}</strong>
                                    @forelse ($candidate['doubles'] as $warning)<span class="mt-2 block text-danger">{{ $warning }}</span>
                                    @empty<span class="mt-1 block text-muted">Sem conflito de horário ou dobra identificados.</span>@endforelse
                                </span>
                            </label>
                        @empty
                            <p class="text-sm font-semibold">Nenhum profissional disponível para este plantão.</p>
                            <p class="mt-2 text-sm text-muted">Confira os cadastros do setor e da categoria ou consulte a escala. Nenhuma atribuição será alterada.</p>
                        @endforelse
                    </div>
                </fieldset>
                <p id="reallocation-help" class="text-xs text-muted">A disponibilidade será verificada novamente antes de salvar. Este fluxo não define novos limites de carga horária ou autorizações de dobra.</p>
            @endif
        </div>
    @endif
    <x-slot:actions>
        <button type="button" wire:click="closeReallocation" class="button-secondary w-full sm:w-auto" data-confirm-cancel wire:loading.attr="disabled" wire:target="confirmReallocation, reviewReallocation">Fechar</button>
        @if ($reallocationPreview)
            <button type="button" wire:click="returnToReallocation" class="button-secondary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="confirmReallocation">Voltar à seleção</button>
            <button type="button" wire:click="confirmReallocation" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="confirmReallocation" @disabled($errors->has('reallocation') || $errors->has('reallocationUserId'))>
                <span wire:loading.remove wire:target="confirmReallocation">Confirmar realocação</span><span wire:loading wire:target="confirmReallocation" role="status">Salvando…</span>
            </button>
        @else
            <button type="button" wire:click="reviewReallocation" class="button-primary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="reviewReallocation" @disabled(! $reallocationCandidates || $errors->has('reallocation'))>
                <span wire:loading.remove wire:target="reviewReallocation">Revisar alteração</span><span wire:loading wire:target="reviewReallocation">Verificando…</span>
            </button>
        @endif
    </x-slot:actions>
</x-confirmation-modal>
