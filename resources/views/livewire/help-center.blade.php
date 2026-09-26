<div>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Central de orientação</p>
            <h1 class="page-title">Ajuda e versões</h1>
            <p class="page-description">Consulte o manual de uso do Escala Fácil, as regras principais e tudo o que mudou em cada versão.</p>
        </div>
        <div class="current-version-card">
            <span class="meta-label">Versão atual</span>
            <strong>v{{ $currentRelease['version'] }}</strong>
            <span>{{ \Carbon\CarbonImmutable::parse($currentRelease['date'])->format('d/m/Y') }}</span>
        </div>
    </div>

    <nav class="help-shortcuts mt-8" aria-label="Atalhos do manual">
        <a href="#primeiros-passos"><x-nav-icon name="home" class="size-5" /><span>Primeiros passos</span></a>
        <a href="#manual-gerencia"><x-nav-icon name="schedule" class="size-5" /><span>Manual da gerência</span></a>
        <a href="#manual-profissional"><x-nav-icon name="calendar" class="size-5" /><span>Manual do profissional</span></a>
        <a href="#regras-status"><x-nav-icon name="help" class="size-5" /><span>Regras e status</span></a>
        <a href="#historico-versoes"><x-nav-icon name="sparkles" class="size-5" /><span>Histórico de versões</span></a>
    </nav>

    <section id="primeiros-passos" class="help-section mt-8 scroll-mt-6" aria-labelledby="first-steps-title">
        <div class="help-section-heading">
            <span>01</span>
            <div><p class="eyebrow">Base do sistema</p><h2 id="first-steps-title">Primeiros passos</h2></div>
        </div>
        <div class="help-content-grid">
            <article>
                <h3>Acesso e perfil</h3>
                <p>Entre com o e-mail e a senha fornecidos pela gerência. O sistema identifica seu perfil e abre automaticamente a área correta. Use “Esqueci minha senha” na tela de login quando precisar redefinir a credencial.</p>
            </article>
            <article>
                <h3>Navegação</h3>
                <p>No desktop, use o controle no topo da sidebar para recolher ou expandir o menu. No celular, toque no botão de menu para abrir a gaveta. A preferência da sidebar fica salva neste navegador.</p>
            </article>
            <article>
                <h3>Leitura de estados</h3>
                <p>Badges e mensagens informam se um plantão está agendado, descoberto ou com ausência. As cores ajudam na leitura, mas o texto do estado é sempre a referência principal.</p>
            </article>
            <article>
                <h3>Novidades</h3>
                <p>Ao entrar pela primeira vez depois de uma atualização, leia o modal de novidades e escolha “Entendi, continuar”. O histórico completo permanece disponível nesta página.</p>
            </article>
        </div>
    </section>

    <section id="manual-gerencia" class="help-section mt-8 scroll-mt-6" aria-labelledby="manager-manual-title">
        <div class="help-section-heading">
            <span>02</span>
            <div><p class="eyebrow">Perfil gerente</p><h2 id="manager-manual-title">Manual da gerência</h2></div>
        </div>
        <div class="help-accordion">
            <details open>
                <summary>Visão geral e alertas</summary>
                <div><p>O painel apresenta plantões agendados no mês, futuros descobertos e trocas aguardando decisão. Use os alertas e atalhos para abrir diretamente o módulo relacionado. Um valor zero significa que a consulta não encontrou pendências.</p></div>
            </details>
            <details>
                <summary>Calendário mensal</summary>
                <div><p>Abra <strong>Calendário</strong>, altere o mês pelas setas ou pelo seletor e escolha um dia. Abaixo do calendário aparecem todos os plantões daquele dia com profissional, horário, setor, categoria e status. O marcador vermelho indica plantão sem profissional.</p></div>
            </details>
            <details>
                <summary>Criar, editar e excluir plantões</summary>
                <div><ol><li>Abra <strong>Escalas</strong> e selecione o mês.</li><li>Use <strong>Adicionar plantão</strong> para abrir o formulário.</li><li>Informe data, horários, setor e categoria; escolha um profissional compatível ou deixe sem alocação.</li><li>Salve para atualizar a lista. Use <strong>Editar</strong> para corrigir e <strong>Excluir</strong> para remover após a confirmação.</li></ol><p>O sistema bloqueia profissionais de categoria ou setor incompatíveis.</p></div>
            </details>
            <details>
                <summary>Analisar trocas</summary>
                <div><p>Em <strong>Trocas</strong>, revise as solicitações que já foram aceitas pelo substituto. Confira plantão, solicitante e substituto antes de aprovar. A aprovação transfere o plantão; a rejeição encerra o pedido sem alterar a escala.</p></div>
            </details>
            <details>
                <summary>Gerenciar setores e categorias</summary>
                <div><p>Use <strong>Adicionar setor</strong> ou <strong>Adicionar categoria</strong> para cadastrar. A edição ocorre no mesmo modal. A exclusão é bloqueada quando o cadastro está vinculado a usuários ou plantões, preservando o histórico.</p></div>
            </details>
            <details>
                <summary>Gerenciar usuários</summary>
                <div><p>Em <strong>Usuários</strong>, pesquise por nome ou e-mail e filtre pelo perfil. Ao criar um profissional, informe categoria e setor. Gerentes não usam esses vínculos. O sistema protege a própria conta do gerente, o último gerente ativo e usuários com histórico operacional.</p></div>
            </details>
        </div>
    </section>

    <section id="manual-profissional" class="help-section mt-8 scroll-mt-6" aria-labelledby="professional-manual-title">
        <div class="help-section-heading">
            <span>03</span>
            <div><p class="eyebrow">Perfil profissional</p><h2 id="professional-manual-title">Manual do profissional</h2></div>
        </div>
        <div class="help-accordion">
            <details open>
                <summary>Consultar minha escala</summary>
                <div><p>Abra <strong>Minha escala</strong> e selecione o mês. Cada cartão mostra data, horário, setor, categoria e situação do plantão. Pedidos de troca recebidos e enviados aparecem na mesma área.</p></div>
            </details>
            <details>
                <summary>Solicitar uma troca</summary>
                <div><ol><li>Localize um plantão futuro atribuído a você.</li><li>Escolha <strong>Solicitar troca</strong>.</li><li>Selecione um colega elegível e confirme.</li></ol><p>Somente profissionais do mesmo setor e categoria aparecem como opção. O pedido fica aguardando o colega.</p></div>
            </details>
            <details>
                <summary>Aceitar uma solicitação</summary>
                <div><p>Nos pedidos recebidos, confira os dados do plantão antes de aceitar. Depois do aceite, a troca segue para decisão da gerência e a escala ainda não muda até a aprovação.</p></div>
            </details>
            <details>
                <summary>Informar ausência</summary>
                <div><p>No plantão correspondente, use <strong>Informar ausência</strong>, descreva o motivo e anexe o comprovante quando disponível. O arquivo fica em armazenamento privado. Ao confirmar, o plantão passa a descoberto e a gerência recebe o alerta.</p></div>
            </details>
        </div>
    </section>

    <section id="regras-status" class="help-section mt-8 scroll-mt-6" aria-labelledby="rules-title">
        <div class="help-section-heading">
            <span>04</span>
            <div><p class="eyebrow">Referência rápida</p><h2 id="rules-title">Regras e status</h2></div>
        </div>
        <div class="help-content-grid">
            <article><h3>Limite de trocas</h3><p>Cada profissional pode participar de até quatro trocas aprovadas por mês, somando pedidos próprios e substituições.</p></article>
            <article><h3>Compatibilidade</h3><p>Uma troca ou alocação exige que o profissional pertença ao mesmo setor e à mesma categoria do plantão.</p></article>
            <article><h3>Fluxo da troca</h3><p>Aguardando profissional → Aguardando gerência → Aprovada ou Rejeitada.</p></article>
            <article><h3>Cobertura</h3><p>Agendado possui cobertura; Ausência informada registra a falta; Descoberto exige nova alocação.</p></article>
        </div>
    </section>

    <section id="historico-versoes" class="help-section mt-8 scroll-mt-6" aria-labelledby="versions-title">
        <div class="help-section-heading">
            <span>05</span>
            <div><p class="eyebrow">Controle de versão</p><h2 id="versions-title">Histórico de versões</h2></div>
        </div>
        <div class="release-timeline">
            @foreach ($releases as $release)
                <article class="release-entry {{ $release['version'] === $currentRelease['version'] ? 'release-entry-current' : '' }}">
                    <div class="release-entry-meta">
                        <span>v{{ $release['version'] }}</span>
                        <time datetime="{{ $release['date'] }}">{{ \Carbon\CarbonImmutable::parse($release['date'])->format('d/m/Y') }}</time>
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3>{{ $release['title'] }}</h3>
                            @if ($release['version'] === $currentRelease['version'])<span class="status-badge status-scheduled">Atual</span>@endif
                        </div>
                        <p>{{ $release['summary'] }}</p>
                        <ul>@foreach ($release['highlights'] as $highlight)<li>{{ $highlight }}</li>@endforeach</ul>
                    </div>
                </article>
            @endforeach
        </div>
    </section>
</div>
