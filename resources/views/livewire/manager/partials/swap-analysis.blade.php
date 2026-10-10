<x-confirmation-modal
    :show="$pendingDecisionSwapId !== null"
    id="decide-swap"
    :title="$pendingDecision === 'approve' ? 'Aprovar e transferir plantão?' : ($pendingDecision === 'reject' ? 'Rejeitar solicitação de troca?' : 'Analisar pedido #'.$pendingDecisionSwapId)"
    :eyebrow="$pendingDecision ? 'Confirmação necessária' : 'Análise da troca'"
    close-action="cancelDecision"
    :tone="$pendingDecision === 'reject' ? 'danger' : 'primary'"
    busy-action="confirmDecision"
    trap-focus
>
    @if ($pendingDecisionSummary)
        <div class="space-y-3 text-left" data-swap-analysis>
            <div class="rounded-lg border border-line bg-canvas p-3">
                <p class="meta-label">Plantão a ser substituído</p>
                <p class="mt-1 font-semibold text-ink">{{ $pendingDecisionSummary['shift'] }}</p>
                <p class="text-sm text-muted">{{ $pendingDecisionSummary['location'] }}</p>
            </div>
            @if ($pendingDecisionSummary['counterpart'])
                <div class="rounded-lg border border-line bg-canvas p-3">
                    <p class="meta-label">Plantão de contrapartida</p>
                    <p class="mt-1 font-semibold text-ink">{{ $pendingDecisionSummary['counterpart'] }}</p>
                    <p class="text-sm text-muted">{{ $pendingDecisionSummary['counterpart_location'] }}</p>
                </div>
            @endif
            <p class="text-sm">Solicitante: <strong>{{ $pendingDecisionSummary['requester'] }}</strong><br>Substituto: <strong>{{ $pendingDecisionSummary['substitute'] }}</strong></p>
            <div class="rounded-lg border border-line p-3 text-sm">
                <p class="font-semibold">Troca geral:</p>
                <p>{{ $pendingDecisionSummary['substitute'] }} assume {{ $pendingDecisionSummary['shift'] }}.</p>
                @if ($pendingDecisionSummary['counterpart'])<p>{{ $pendingDecisionSummary['requester'] }} assume {{ $pendingDecisionSummary['counterpart'] }}.</p>@endif
            </div>
            <div class="text-sm">
                <p class="font-semibold">Limite mensal</p>
                @foreach ($pendingDecisionSummary['quotas'] as $quota)<p>{{ $quota }}</p>@endforeach
                <p class="mt-1 text-xs text-muted">Contagem pelo mês da solicitação, conforme a regra vigente.</p>
            </div>
            @forelse ($pendingDecisionSummary['warnings'] as $warning)
                <p class="border-l-2 border-danger pl-3 text-sm font-semibold text-danger">{{ $warning }}</p>
            @empty
                <p class="text-sm text-muted">Nenhuma dobra, conflito ou limite mensal atingido identificado nesta análise.</p>
            @endforelse
            @if (! $pendingDecisionSummary['can_approve'])<p class="text-sm text-danger">A aprovação está indisponível. Confira a atribuição dos plantões e o limite mensal. Ainda é possível rejeitar com justificativa.</p>@endif
            @error('decision')<p class="field-error" role="alert">{{ $message }}</p>@enderror
            <p class="field-error" role="alert" tabindex="-1" hidden data-decision-request-error></p>
            @if ($pendingDecision === 'reject')
                <div>
                    <label for="rejection-reason" class="field-label">Justificativa da rejeição <span class="text-danger">*</span></label>
                    <textarea wire:model="rejectionReason" id="rejection-reason" rows="3" maxlength="1000" required class="field-input min-h-24" aria-describedby="rejection-reason-help{{ $errors->has('rejectionReason') ? ' rejection-reason-error' : '' }}" @error('rejectionReason') aria-invalid="true" @enderror></textarea>
                    @error('rejectionReason')<p id="rejection-reason-error" class="field-error" role="alert">{{ $message }}</p>@enderror
                    <p id="rejection-reason-help" class="mt-1 text-xs text-muted">A justificativa será enviada aos dois profissionais e registrada no comprovante.</p>
                </div>
                <p class="text-sm">Confirme para rejeitar o pedido e manter as atribuições dos plantões.</p>
            @elseif ($pendingDecision === 'approve')
                <p class="text-sm">Confirme para transferir os plantões indicados. Ambos receberão o comprovante da decisão.</p>
            @endif
        </div>
    @endif
    <x-slot:actions>
        <button type="button" wire:click="cancelDecision" class="button-secondary w-full sm:w-auto" data-confirm-cancel wire:loading.attr="disabled" wire:target="confirmDecision">Fechar</button>
        @if ($pendingDecision)
            <button type="button" wire:click="returnToSwapAnalysis" class="button-secondary w-full sm:w-auto" wire:loading.attr="disabled" wire:target="confirmDecision">Voltar à análise</button>
            <button type="button" wire:click="confirmDecision" wire:loading.attr="disabled" wire:target="confirmDecision" @disabled($errors->has('decision')) class="{{ $pendingDecision === 'approve' ? 'button-primary' : 'button-danger' }} w-full sm:w-auto">
                <span wire:loading.remove wire:target="confirmDecision">{{ $pendingDecision === 'approve' ? 'Confirmar aprovação' : 'Confirmar rejeição' }}</span>
                <span wire:loading wire:target="confirmDecision" role="status">Processando…</span>
            </button>
        @else
            <button type="button" wire:click="requestDecision({{ $pendingDecisionSwapId }}, 'reject')" wire:loading.attr="disabled" wire:target="requestDecision" class="button-secondary w-full sm:w-auto">Rejeitar…</button>
            <button type="button" wire:click="requestDecision({{ $pendingDecisionSwapId }}, 'approve')" wire:loading.attr="disabled" wire:target="requestDecision" @disabled(! ($pendingDecisionSummary['can_approve'] ?? false)) class="button-primary w-full sm:w-auto">Aprovar…</button>
        @endif
    </x-slot:actions>
</x-confirmation-modal>
