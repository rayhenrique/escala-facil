<div class="col-span-full space-y-4" tabindex="-1" x-ref="alerts">
    <h3 class="text-lg font-semibold">Revise antes de confirmar</h3>
    <p class="text-sm">{{ $shiftPreview['period'] }} · {{ $shiftPreview['professional'] }}</p>
    <x-shift-assignment-alerts :blocks="$shiftPreview['blocks']" :warnings="$shiftPreview['warnings']" />
    <p class="text-sm text-muted">Os avisos não criam novos limites institucionais. A disponibilidade será conferida novamente ao confirmar.</p>
    <button type="button" wire:click="returnToShiftForm" :disabled="busy" class="button-secondary">Revisar dados</button>
</div>
