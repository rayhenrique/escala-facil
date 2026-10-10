<section id="simulacao" tabindex="-1" aria-labelledby="workflow-title" class="gsap-simulation-section landing-section border-y border-line bg-white">
    <div class="landing-container">
        <div class="landing-section-heading">
            <p class="eyebrow">Como uma troca acontece</p>
            <h2 id="workflow-title" class="landing-title">Um pedido, três etapas claras.</h2>
            <p class="landing-description">Este exemplo mostra o caminho de uma troca aprovada. A confirmação depende do aceite do colega e da análise da gerência; um pedido também pode ser rejeitado.</p>
        </div>
        <div class="gsap-simulation-card rounded-xl border border-line bg-canvas p-5 sm:p-7">
            <p class="text-sm font-semibold text-ink">Exemplo ilustrativo · substituição de um plantão na UTI</p>
            <div aria-hidden="true" class="my-5 h-1 overflow-hidden rounded-full bg-line"><div class="gsap-sim-progress h-full w-0 bg-primary"></div></div>
            <ol class="grid gap-6 lg:grid-cols-3">
                <li><p class="eyebrow">1 · Solicitação</p><h3 class="mt-2 text-lg font-semibold">O profissional envia o pedido</h3><p class="mt-2 text-sm leading-6 text-muted">Escolhe o plantão e um colega compatível para a substituição.</p><div class="mt-4"><x-status-badge :status="\App\Enums\ShiftSwapStatus::PendingProfessional" /></div></li>
                <li><p class="eyebrow">2 · Aceite</p><h3 class="mt-2 text-lg font-semibold">O colega aceita a proposta</h3><p class="mt-2 text-sm leading-6 text-muted">A solicitação segue para a gerência. O aceite ainda não confirma a troca.</p><div class="mt-4"><x-status-badge :status="\App\Enums\ShiftSwapStatus::PendingManager" /></div></li>
                <li><p class="eyebrow">3 · Decisão</p><h3 class="mt-2 text-lg font-semibold">A gerência aprova a troca</h3><p class="mt-2 text-sm leading-6 text-muted">Neste exemplo, a escala é atualizada e os envolvidos recebem o comprovante.</p><div class="mt-4"><x-status-badge :status="\App\Enums\ShiftSwapStatus::Approved" /></div></li>
            </ol>
        </div>
    </div>
</section>
