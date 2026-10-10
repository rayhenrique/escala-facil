<section id="superpoderes" tabindex="-1" aria-labelledby="features-title" class="landing-section">
    <div class="landing-container">
        <div class="landing-section-heading">
            <p class="eyebrow">Recursos do aplicativo</p>
            <h2 id="features-title" class="landing-title">Da organização da escala<br class="hidden sm:block" /> ao acompanhamento do plantão.</h2>
            <p class="landing-description">Cada perfil tem ferramentas para sua rotina: a gerência organiza e decide; o profissional consulta, solicita e responde.</p>
        </div>
        <div class="gsap-bento-grid grid gap-5 lg:grid-cols-3">
            <article class="gsap-bento-card landing-feature">
                <p class="eyebrow">Trocas</p>
                <h3>Solicitar e acompanhar</h3>
                <p class="landing-feature-description">Peça uma substituição simples ou uma troca com contrapartida. Consulte a situação até a decisão da gerência.</p>
                <ul class="landing-benefits"><li>Seleção por setor e categoria compatíveis</li><li>Controle do limite mensal de trocas</li><li>Alertas de dobra para análise da gestão</li></ul>
                <div class="landing-feature-example"><span class="text-sm font-semibold">Situação da solicitação</span><x-status-badge :status="\App\Enums\ShiftSwapStatus::PendingProfessional" /></div>
            </article>
            <article class="gsap-bento-card landing-feature">
                <p class="eyebrow">Calendário</p>
                <h3>Consultar a agenda</h3>
                <p class="landing-feature-description">Veja o mês e consulte os plantões de cada dia, com horários e duração apresentados de forma clara.</p>
                <ul class="landing-benefits"><li>Filtros por setor e categoria</li><li>Identificação de turnos noturnos e de 24h</li><li>Consulta no computador e no celular</li></ul>
                <div class="landing-feature-example"><span class="text-sm font-semibold">Exemplo de plantão de 24h</span><p class="text-sm leading-6 text-muted">07:00–07:00 · dia seguinte · 24h</p></div>
            </article>
            <article class="gsap-bento-card landing-feature">
                <p class="eyebrow">Gestão</p>
                <h3>Conferir e registrar</h3>
                <p class="landing-feature-description">Acompanhe cobertura e pendências. Consulte as decisões de troca e os comprovantes de cada evento.</p>
                <ul class="landing-benefits"><li>Painel de cobertura e plantões descobertos</li><li>Histórico de notificações no aplicativo</li><li>Relatórios e comprovantes em PDF</li></ul>
                <div class="landing-feature-example"><span class="text-sm font-semibold">Registro da decisão</span><x-status-badge :status="\App\Enums\ShiftSwapStatus::Approved" /></div>
            </article>
        </div>
    </div>
</section>
