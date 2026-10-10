<figure class="landing-preview">
    <figcaption class="flex flex-wrap items-center justify-between gap-2 border-b border-line px-5 py-4">
        <span class="font-semibold text-ink">Sua agenda de plantões</span>
        <span class="text-xs text-muted">Prévia · dados ilustrativos</span>
    </figcaption>
    <div class="space-y-5 p-5 sm:p-6">
        <div>
            <h2 class="eyebrow">Próximo plantão</h2>
            <div class="mt-3"><x-shift-card :shift="$shift" /></div>
        </div>
        <div class="border-t border-line pt-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="font-semibold">Sua solicitação de troca</h2>
                <x-status-badge :status="\App\Enums\ShiftSwapStatus::PendingManager" />
            </div>
            <p class="mt-3 text-sm leading-6 text-muted">O colega aceitou a substituição. A troca aguarda a decisão da gerência.</p>
        </div>
    </div>
    <p class="border-t border-line bg-canvas px-5 py-4 text-sm leading-6 text-muted">Confira início, término e duração, inclusive nos turnos que atravessam a meia-noite.</p>
</figure>
