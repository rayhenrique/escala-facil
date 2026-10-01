<div>
    <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <p class="eyebrow">Central de orientação</p>
            <h1 class="page-title">Ajuda e versões</h1>
            <p class="page-description">Siga a ordem inicial de configuração, consulte as rotinas de cada perfil e encontre orientações para as situações mais comuns do Escala Fácil.</p>
        </div>
        <div class="current-version-card">
            <span class="meta-label">Versão atual</span>
            <strong>v{{ $currentRelease['version'] }}</strong>
            <span>{{ \Carbon\CarbonImmutable::parse($currentRelease['date'])->format('d/m/Y') }}</span>
        </div>
    </div>

    <nav class="help-shortcuts mt-8" aria-label="Atalhos do manual">
        <a href="#ordem-inicial"><x-nav-icon name="home" class="size-5" /><span>Comece por aqui</span></a>
        <a href="#manual-gerencia"><x-nav-icon name="schedule" class="size-5" /><span>Rotina da gerência</span></a>
        <a href="#manual-profissional"><x-nav-icon name="calendar" class="size-5" /><span>Rotina profissional</span></a>
        <a href="#trocas-ausencias"><x-nav-icon name="swap" class="size-5" /><span>Trocas e ausências</span></a>
        <a href="#problemas-comuns"><x-nav-icon name="help" class="size-5" /><span>Solução de problemas</span></a>
        <a href="#instalar-aplicativo"><x-nav-icon name="install" class="size-5" /><span>Instalar aplicativo</span></a>
        <a href="#historico-versoes"><x-nav-icon name="sparkles" class="size-5" /><span>Histórico de versões</span></a>
    </nav>

    <section id="ordem-inicial" class="help-section mt-8 scroll-mt-6" aria-labelledby="initial-order-title">
        <div class="help-section-heading">
            <span>01</span>
            <div><p class="eyebrow">Implantação inicial</p><h2 id="initial-order-title">Ordem correta para começar</h2></div>
        </div>

        <div class="manual-callout mt-6" role="note">
            <strong>Faça esta sequência uma vez, usando uma conta de gerente.</strong>
            <p>Os cadastros dependem uns dos outros. Categorias e setores precisam existir antes dos profissionais, e os profissionais precisam estar cadastrados antes da alocação dos plantões.</p>
        </div>

        <ol class="manual-roadmap" aria-label="Sequência inicial de configuração">
            <li>
                <span class="manual-step-number">1</span>
                <div><h3>Cadastre as categorias</h3><p>Registre as especialidades ou funções profissionais exigidas nos plantões, como Médico, Enfermeiro ou Técnico de Enfermagem.</p><a href="#gerenciar-categorias">Ver instruções de categorias</a></div>
            </li>
            <li>
                <span class="manual-step-number">2</span>
                <div><h3>Cadastre os setores</h3><p>Registre os locais ou áreas de atendimento, como Pronto Atendimento, UTI ou Clínica Médica.</p><a href="#gerenciar-setores">Ver instruções de setores</a></div>
            </li>
            <li>
                <span class="manual-step-number">3</span>
                <div><h3>Cadastre os usuários</h3><p>Crie as contas gerenciais e profissionais. Todo profissional deve receber uma categoria e um setor compatíveis com sua atuação.</p><a href="#gerenciar-usuarios">Ver instruções de usuários</a></div>
            </li>
            <li>
                <span class="manual-step-number">4</span>
                <div><h3>Crie os plantões</h3><p>Informe data, horário, setor e categoria. Aloque um profissional compatível ou deixe o plantão descoberto até definir a cobertura.</p><a href="#gerenciar-plantoes">Ver instruções de plantões</a></div>
            </li>
            <li>
                <span class="manual-step-number">5</span>
                <div><h3>Confira o calendário</h3><p>Selecione os próximos dias e confirme horários, plantonistas e eventuais plantões sem profissional.</p><a href="#consultar-calendario">Ver instruções do calendário</a></div>
            </li>
            <li>
                <span class="manual-step-number">6</span>
                <div><h3>Inicie a operação diária</h3><p>Use a Visão geral para acompanhar cobertura, trocas aguardando decisão e alertas de ausência.</p><a href="#rotina-gerencial">Ver a rotina gerencial</a></div>
            </li>
        </ol>

        <div class="help-content-grid">
            <article>
                <h3>Acesso à conta</h3>
                <p>Entre com o e-mail e a senha fornecidos pela gerência. O perfil da conta define automaticamente a área exibida. Use <strong>Esqueci minha senha</strong> na tela de acesso quando precisar redefinir a credencial.</p>
            </article>
            <article>
                <h3>Navegação no desktop</h3>
                <p>A sidebar permanece visível. Use o controle no topo para recolher ou expandir o menu; a preferência fica salva neste navegador. O item destacado indica a página atual.</p>
            </article>
            <article>
                <h3>Navegação no celular</h3>
                <p>Toque em <strong>Abrir menu principal</strong> para mostrar a gaveta lateral. Ela fecha ao escolher uma página, tocar fora do menu ou usar o botão de fechar.</p>
            </article>
            <article>
                <h3>Novidades da versão</h3>
                <p>Depois de uma atualização, o primeiro acesso apresenta as novidades. Escolha <strong>Entendi, continuar</strong> para entrar ou abra este manual. O aviso aparece uma vez para cada versão.</p>
            </article>
            <article>
                <h3>Confirmações e mensagens</h3>
                <p>Ações importantes abrem um modal com o registro e a consequência. Leia o resumo e escolha o botão de confirmação ou volte para revisar. Depois da ação, um aviso flutuante informa sucesso ou explica por que ela não pôde ser concluída.</p>
            </article>
        </div>
    </section>

    <section id="manual-gerencia" class="help-section mt-8 scroll-mt-6" aria-labelledby="manager-manual-title">
        <div class="help-section-heading">
            <span>02</span>
            <div><p class="eyebrow">Perfil gerente</p><h2 id="manager-manual-title">Configuração e rotina da gerência</h2></div>
        </div>
        <p class="manual-section-intro">Os tópicos abaixo seguem a mesma ordem recomendada para preparar o sistema e conduzir a operação.</p>

        <div class="help-accordion">
            <details id="gerenciar-categorias" open>
                <summary>1. Cadastrar e manter categorias</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Categorias</strong> na sidebar.</li>
                        <li>Selecione <strong>Adicionar categoria</strong>.</li>
                        <li>Informe um nome claro para a função ou especialidade e salve.</li>
                        <li>Use <strong>Editar</strong> para corrigir o nome sem perder os vínculos existentes.</li>
                        <li>Use <strong>Excluir</strong> somente quando a categoria não estiver vinculada a usuários ou plantões. Confira o nome no modal e confirme a exclusão.</li>
                    </ol>
                    <p><strong>Resultado esperado:</strong> a categoria passa a aparecer nos formulários de profissionais e plantões. Se a exclusão for bloqueada, mantenha o cadastro para preservar o histórico operacional.</p>
                </div>
            </details>
            <details id="gerenciar-setores">
                <summary>2. Cadastrar e manter setores</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Setores</strong> na sidebar.</li>
                        <li>Selecione <strong>Adicionar setor</strong>.</li>
                        <li>Informe o nome do local ou área de atendimento e salve.</li>
                        <li>Use <strong>Editar</strong> quando precisar atualizar a identificação.</li>
                        <li>Exclua apenas setores sem usuários ou plantões vinculados; o modal mostra o setor antes da confirmação.</li>
                    </ol>
                    <p><strong>Boa prática:</strong> use nomes reconhecidos pela equipe e evite cadastros duplicados com abreviações diferentes.</p>
                </div>
            </details>
            <details id="gerenciar-usuarios">
                <summary>3. Cadastrar e manter usuários</summary>
                <div>
                    <h4>Criar uma conta</h4>
                    <ol>
                        <li>Abra <strong>Usuários</strong> e selecione <strong>Adicionar usuário</strong>.</li>
                        <li>Informe nome completo e um e-mail exclusivo.</li>
                        <li>Escolha o perfil <strong>Gerente</strong> ou <strong>Profissional</strong>.</li>
                        <li>Para um profissional, selecione obrigatoriamente a categoria e o setor.</li>
                        <li>Defina e confirme a senha inicial, depois salve.</li>
                    </ol>
                    <h4>Pesquisar, editar e excluir</h4>
                    <ul>
                        <li>Use a busca por nome ou e-mail e o filtro de perfil para localizar uma conta.</li>
                        <li>Na edição, deixe a senha vazia para mantê-la; preencha somente quando quiser substituí-la.</li>
                        <li>O sistema bloqueia alterações de perfil, categoria ou setor que tornariam plantões futuros incompatíveis.</li>
                        <li>A própria conta gerencial não pode ser rebaixada, e o último gerente precisa permanecer no sistema.</li>
                        <li>Usuários com histórico de plantões, trocas ou ausências não podem ser excluídos. O modal identifica a conta e o aviso informa quando a preservação do histórico bloqueia a ação.</li>
                    </ul>
                </div>
            </details>
            <details id="gerenciar-plantoes">
                <summary>4. Criar, editar, alocar e excluir plantões</summary>
                <div>
                    <h4>Criar um plantão</h4>
                    <ol>
                        <li>Abra <strong>Escalas</strong>, escolha o mês e selecione <strong>Adicionar plantão</strong>.</li>
                        <li>Informe uma data atual ou futura.</li>
                        <li>Defina o horário inicial e o final; o encerramento deve ser posterior ao início.</li>
                        <li>Selecione setor e categoria.</li>
                        <li>Escolha um profissional elegível ou deixe sem profissional para registrar um plantão descoberto.</li>
                        <li>Salve e confira a mensagem de sucesso.</li>
                    </ol>
                    <h4>Manutenção</h4>
                    <ul>
                        <li>Use <strong>Editar</strong> para ajustar os dados; somente profissionais do mesmo setor e categoria ficam disponíveis.</li>
                        <li>Antes de excluir, confira no modal a data, o horário, o setor e a categoria. A exclusão remove o plantão e seus registros associados de troca e ausência.</li>
                        <li>Quando uma ausência é informada, o plantão perde a alocação e passa a exigir nova cobertura.</li>
                    </ul>
                    <h4>Importar e exportar plantões (XLSX)</h4>
                    <ol>
                        <li>Baixe o <strong>Modelo XLSX</strong> em Escalas: ele já traz os setores e categorias cadastrados para referência.</li>
                        <li>Preencha data (DD/MM/AAAA), início e fim (HH:MM), setor e categoria com os nomes exatos do modelo, e o e-mail do profissional quando houver alocação.</li>
                        <li>Use <strong>Exportar</strong> para baixar os plantões do mês exibido e aproveitá-los como base de edição.</li>
                        <li>Envie o arquivo em <strong>Importar</strong>: linhas duplicadas são ignoradas, e qualquer erro em uma linha cancela toda a importação com o motivo indicado.</li>
                    </ol>
                </div>
            </details>
            <details id="consultar-calendario">
                <summary>5. Conferir o calendário mensal</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Calendário</strong>.</li>
                        <li>Navegue pelas setas ou selecione diretamente o mês desejado.</li>
                        <li>Observe a quantidade de plantões em cada dia e o indicador de itens descobertos.</li>
                        <li>O marcador ✓ indica os dias com 24 horas cobertas por plantões com profissional.</li>
                        <li>Use os filtros de setor e categoria para focar em parte da escala; limpe os filtros para voltar à visão completa.</li>
                        <li>Selecione um dia para listar todos os plantões com horário, setor, categoria, profissional e status.</li>
                        <li>Use os atalhos de gestão para corrigir a escala quando necessário.</li>
                    </ol>
                    <p>O calendário também está disponível aos profissionais para consulta, mas somente a gerência visualiza ações administrativas.</p>
                </div>
            </details>
            <details id="rotina-gerencial">
                <summary>6. Executar a rotina gerencial diária</summary>
                <div>
                    <h4>Ao iniciar o dia</h4>
                    <ol>
                        <li>Abra <strong>Visão geral</strong> e confira os plantões programados, descobertos e as trocas pendentes.</li>
                        <li>Abra os alertas de cobertura e realoque profissionais quando necessário.</li>
                        <li>Acesse <strong>Trocas</strong> para analisar pedidos já aceitos pelo substituto.</li>
                        <li>Confira <strong>Notificações</strong> e abra os comprovantes ainda não lidos.</li>
                    </ol>
                    <h4>Antes de encerrar</h4>
                    <ul>
                        <li>Confirme a cobertura dos próximos dias no calendário.</li>
                        <li>Verifique se não há decisões gerenciais pendentes.</li>
                        <li>Revise alertas gerados por ausências e providencie a nova alocação.</li>
                    </ul>
                    <h4>Fechamento do mês</h4>
                    <ol>
                        <li>Abra <strong>Relatórios</strong> e selecione o mês, o setor e a categoria.</li>
                        <li>Confira cobertura, trocas, ausências e o desempenho por profissional.</li>
                        <li>Use <strong>Exportar PDF</strong> para guardar ou compartilhar o fechamento.</li>
                        <li>Use <strong>PDF para afixar</strong> para imprimir a escala do mês com legenda, trocas destacadas e assinaturas.</li>
                    </ol>
                </div>
            </details>
            <details>
                <summary>7. Aprovar ou rejeitar trocas</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Trocas</strong>. A fila gerencial mostra apenas pedidos já aceitos pelo substituto.</li>
                        <li>Confira data, horário, setor, categoria, solicitante e substituto.</li>
                        <li>Selecione <strong>Aprovar</strong> para transferir o plantão ao substituto, ou <strong>Rejeitar</strong> para encerrar o pedido sem mudar a escala.</li>
                        <li>No modal, revise o plantão, o solicitante, o substituto e a consequência; depois confirme a decisão.</li>
                        <li>Leia o aviso e o comprovante da decisão. Guarde a referência quando precisar localizar o evento depois.</li>
                        <li>O selo <strong>Dobra</strong> indica que o substituto emenda turnos; aprove somente se a dobra for autorizada.</li>
                    </ol>
                    <p>A decisão avisa os dois profissionais. A aprovação ainda revalida compatibilidade e limite mensal antes de alterar o plantão.</p>
                    <p>Para corrigir uma troca já aprovada, use <strong>Desfazer troca</strong> na lista de aprovadas futuras: o plantão volta ao solicitante, a cota mensal é liberada e ambos recebem comprovante. Só é possível desfazer plantões futuros ainda com o substituto, sem ausência ou nova troca pendente.</p>
                </div>
            </details>
            <details>
                <summary>8. Acompanhar notificações e comprovantes</summary>
                <div>
                    <ul>
                        <li>O contador na sidebar mostra quantas notificações ainda não foram lidas.</li>
                        <li>Abra <strong>Notificações</strong> para consultar o histórico, marcar um item como lido ou marcar todos. A leitura em massa mostra a quantidade e exige confirmação.</li>
                        <li>Cada comprovante registra uma referência, o plantão, os participantes, a situação e o responsável pela ação.</li>
                        <li>Os dados do comprovante permanecem como estavam no momento do evento, mesmo que um cadastro seja alterado depois.</li>
                    </ul>
                </div>
            </details>
        </div>
    </section>

    <section id="manual-profissional" class="help-section mt-8 scroll-mt-6" aria-labelledby="professional-manual-title">
        <div class="help-section-heading">
            <span>03</span>
            <div><p class="eyebrow">Perfil profissional</p><h2 id="professional-manual-title">Rotina do profissional</h2></div>
        </div>
        <div class="help-accordion">
            <details open>
                <summary>Consultar minha escala</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Minha escala</strong>.</li>
                        <li>Escolha o mês que deseja consultar.</li>
                        <li>Confira data, horário, setor, categoria e situação em cada cartão.</li>
                        <li>Use as ações do próprio plantão quando precisar solicitar troca ou informar ausência.</li>
                    </ol>
                    <p>Pedidos enviados e recebidos aparecem na mesma área para facilitar o acompanhamento.</p>
                </div>
            </details>
            <details>
                <summary>Consultar a escala completa no calendário</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Calendário</strong> na sidebar.</li>
                        <li>Navegue até o mês desejado e escolha um dia.</li>
                        <li>Use os filtros de setor e categoria para focar em parte da escala.</li>
                        <li>Consulte todos os plantões e plantonistas daquela data, incluindo horário, setor, categoria e cobertura.</li>
                    </ol>
                    <p>Plantões ainda sem profissional também aparecem. A consulta não permite criar, editar ou excluir registros.</p>
                </div>
            </details>
            <details>
                <summary>Solicitar uma troca</summary>
                <div>
                    <ol>
                        <li>Localize um plantão futuro atribuído a você.</li>
                        <li>Selecione <strong>Solicitar troca</strong>.</li>
                        <li>Escolha um colega elegível e selecione <strong>Revisar solicitação</strong>.</li>
                        <li>Confira plantão e substituto no modal e selecione <strong>Confirmar envio</strong>.</li>
                        <li>Confira a mensagem de sucesso e guarde a referência do comprovante apresentado.</li>
                    </ol>
                    <p>A lista inclui somente colegas do mesmo setor e categoria. O pedido fica como <strong>Aguardando profissional</strong> até o substituto responder.</p>
                </div>
            </details>
            <details>
                <summary>Responder a um pedido recebido</summary>
                <div>
                    <ol>
                        <li>Abra o pedido recebido e confira todos os dados do plantão.</li>
                        <li>Selecione <strong>Aceitar</strong> somente se puder assumir o horário.</li>
                        <li>Revise o plantão e o solicitante no modal e confirme o aceite.</li>
                        <li>Se o aceite emendar com outro plantão seu, o modal avisa a dobra para decisão consciente.</li>
                        <li>Após o aceite, acompanhe o status <strong>Aguardando gerência</strong>.</li>
                        <li>Consulte a notificação final para saber se a troca foi aprovada ou rejeitada.</li>
                    </ol>
                    <p>O aceite ainda não muda a escala. A transferência ocorre somente depois da aprovação gerencial.</p>
                </div>
            </details>
            <details>
                <summary>Informar uma ausência</summary>
                <div>
                    <ol>
                        <li>Localize o plantão de hoje ou uma data futura atribuído a você.</li>
                        <li>Selecione <strong>Informar ausência</strong>.</li>
                        <li>Descreva o motivo de forma clara.</li>
                        <li>Se houver comprovante, anexe um arquivo PDF, JPG ou PNG de até 5 MB.</li>
                        <li>Selecione <strong>Revisar ausência</strong>, confira a consequência no modal e confirme o registro.</li>
                        <li>Confira a mensagem de sucesso apresentada no topo da tela.</li>
                    </ol>
                    <p>O arquivo fica protegido. Depois da confirmação, o plantão passa a descoberto, solicitações de troca abertas são encerradas e a gerência recebe o alerta.</p>
                </div>
            </details>
            <details>
                <summary>Acompanhar notificações e comprovantes</summary>
                <div>
                    <ul>
                        <li>Você recebe aviso ao ser indicado como substituto.</li>
                        <li>Solicitante e substituto recebem aviso depois do aceite e da decisão gerencial.</li>
                        <li>Abra <strong>Notificações</strong> para consultar a referência e o resumo completo de cada movimentação.</li>
                        <li>Marcar uma notificação como lida não apaga o comprovante.</li>
                    </ul>
                </div>
            </details>
        </div>
    </section>

    <section id="trocas-ausencias" class="help-section mt-8 scroll-mt-6" aria-labelledby="critical-flows-title">
        <div class="help-section-heading">
            <span>04</span>
            <div><p class="eyebrow">Fluxos críticos</p><h2 id="critical-flows-title">Trocas, ausências e notificações</h2></div>
        </div>
        <div class="help-content-grid">
            <article>
                <h3>Fluxo completo da troca</h3>
                <ol class="manual-list"><li>Solicitante escolhe o substituto.</li><li>Substituto recebe a notificação.</li><li>Substituto aceita o pedido.</li><li>Gerentes recebem a pendência.</li><li>Gerente aprova ou rejeita.</li><li>Os profissionais recebem a decisão e o comprovante.</li></ol>
            </article>
            <article>
                <h3>Quem recebe cada aviso</h3>
                <ul class="manual-list"><li><strong>Solicitação:</strong> substituto.</li><li><strong>Aceite:</strong> solicitante, substituto e todos os gerentes.</li><li><strong>Aprovação ou rejeição:</strong> solicitante e substituto.</li></ul>
            </article>
            <article>
                <h3>Limite mensal</h3>
                <p>Cada profissional pode participar de até <strong>quatro trocas aprovadas por mês</strong>, somando os pedidos que criou e as substituições que assumiu. A quinta participação é bloqueada.</p>
            </article>
            <article>
                <h3>Compatibilidade obrigatória</h3>
                <p>Alocações e trocas exigem que o profissional pertença ao mesmo setor e à mesma categoria do plantão. A regra é revalidada no aceite e na aprovação.</p>
            </article>
            <article>
                <h3>Efeito da ausência</h3>
                <p>A ausência retira o profissional do plantão, registra a justificativa, encerra trocas ainda abertas e cria uma necessidade de cobertura para a gerência.</p>
            </article>
            <article>
                <h3>Valor do comprovante</h3>
                <p>A referência identifica o evento. O resumo mantém data, horário, setor, categoria, participantes, situação e responsável pela ação para consulta posterior.</p>
            </article>
            <article>
                <h3>Antes de confirmar</h3>
                <p>O modal é a última etapa de revisão. <strong>Voltar</strong> ou <strong>Cancelar</strong> não altera dados. A ação só acontece ao selecionar o botão explícito de envio, aceite, decisão, ausência ou exclusão.</p>
            </article>
        </div>
    </section>

    <section id="regras-status" class="help-section mt-8 scroll-mt-6" aria-labelledby="rules-title">
        <div class="help-section-heading">
            <span>05</span>
            <div><p class="eyebrow">Referência rápida</p><h2 id="rules-title">Status, permissões e segurança</h2></div>
        </div>
        <div class="help-content-grid">
            <article><h3>Status do plantão</h3><ul class="manual-list"><li><strong>Agendado:</strong> possui profissional responsável.</li><li><strong>Descoberto:</strong> aguarda alocação.</li><li><strong>Ausência informada:</strong> a falta foi registrada e exige cobertura.</li></ul></article>
            <article><h3>Status da troca</h3><p><strong>Aguardando profissional</strong> → <strong>Aguardando gerência</strong> → <strong>Aprovada</strong> ou <strong>Rejeitada</strong>.</p></article>
            <article><h3>Permissões</h3><p>Gerentes mantêm cadastros, plantões e decisões. Profissionais consultam escalas e calendário, solicitam ou aceitam trocas e informam as próprias ausências.</p></article>
            <article><h3>Preservação do histórico</h3><p>Cadastros com vínculos operacionais não podem ser excluídos. Essa proteção mantém plantões, trocas, ausências, notificações e comprovantes consistentes.</p></article>
            <article><h3>Proteção de acesso</h3><p>Não compartilhe a senha. Use <strong>Sair</strong> ao terminar em um dispositivo compartilhado e solicite redefinição quando suspeitar de acesso indevido.</p></article>
            <article><h3>Leitura dos indicadores</h3><p>Cores facilitam a leitura, mas o texto é a referência. Um contador zero indica que não existem registros para o filtro informado naquele momento.</p></article>
        </div>
    </section>

    <section id="problemas-comuns" class="help-section mt-8 scroll-mt-6" aria-labelledby="troubleshooting-title">
        <div class="help-section-heading">
            <span>06</span>
            <div><p class="eyebrow">Solução de problemas</p><h2 id="troubleshooting-title">O que fazer quando algo não funciona</h2></div>
        </div>
        <div class="help-accordion">
            <details open>
                <summary>Não consigo entrar</summary>
                <div><p>Confira se o e-mail foi digitado corretamente e se não há espaços antes ou depois. Use <strong>Esqueci minha senha</strong> para solicitar uma nova credencial. Se a mensagem não chegar ou a conta continuar indisponível, peça à gerência para conferir o cadastro e o e-mail informado.</p></div>
            </details>
            <details>
                <summary>Um profissional não aparece para alocação ou troca</summary>
                <div><p>Confirme se a conta está com perfil Profissional e se possui o mesmo setor e a mesma categoria do plantão. Em trocas, o colega também precisa estar dentro do limite mensal e elegível para aquele pedido.</p></div>
            </details>
            <details>
                <summary>Não consigo editar ou excluir um cadastro</summary>
                <div><p>Leia a mensagem exibida. Vínculos com usuários, plantões, trocas ou ausências impedem exclusões que apagariam o contexto do histórico. Alterações de usuário também são bloqueadas quando criariam incompatibilidade com plantões futuros.</p></div>
            </details>
            <details>
                <summary>A ação de troca ou ausência não está disponível</summary>
                <div><p>A ação exige um plantão permitido pela regra: trocas precisam de plantão futuro atribuído ao solicitante e sem outro pedido ativo; ausências aceitam plantões de hoje ou futuros atribuídos ao próprio profissional. O limite mensal também pode impedir uma nova troca.</p></div>
            </details>
            <details>
                <summary>O calendário ou uma lista está vazio</summary>
                <div><p>Confirme o mês selecionado e os filtros ativos. Uma mensagem de estado vazio significa que não há registros correspondentes; navegue para outro mês ou peça à gerência para conferir o cadastro dos plantões.</p></div>
            </details>
            <details>
                <summary>Uma notificação continua com contador</summary>
                <div><p>Abra <strong>Notificações</strong> e use <strong>Marcar como lida</strong> no item, ou marque todas quando já tiver conferido o histórico. Confira a quantidade no modal e confirme. A atualização não remove o comprovante.</p></div>
            </details>
            <details>
                <summary>O modal foi aberto e não quero continuar</summary>
                <div><p>Selecione <strong>Cancelar</strong>, <strong>Voltar</strong> ou pressione <kbd>Esc</kbd>. Nenhum dado é alterado antes da confirmação explícita. Mensagens verdes indicam sucesso; mensagens vermelhas explicam uma ação bloqueada e permanecem visíveis até serem fechadas.</p></div>
            </details>
        </div>
    </section>

    <section id="instalar-aplicativo" class="help-section mt-8 scroll-mt-6" aria-labelledby="install-app-title">
        <div class="help-section-heading">
            <span>07</span>
            <div><p class="eyebrow">Aplicativo instalável</p><h2 id="install-app-title">Como instalar o Escala Fácil</h2></div>
        </div>

        <div class="manual-callout mt-6" role="note">
            <strong>Use sempre o endereço oficial da sua instituição.</strong>
            <p>A instalação não exige loja de aplicativos. Quando o navegador oferecer suporte, o item <strong>Instalar aplicativo</strong> aparece na área Suporte da sidebar e na tela de acesso.</p>
        </div>

        <div class="help-accordion">
            <details open>
                <summary>Instalar no Android com Google Chrome</summary>
                <div>
                    <ol>
                        <li>Abra o endereço do Escala Fácil no <strong>Google Chrome</strong>.</li>
                        <li>Entre no sistema normalmente e aguarde a página terminar de carregar.</li>
                        <li>Se aparecer <strong>Instalar aplicativo</strong> na sidebar, toque nessa opção e confirme em <strong>Instalar</strong>.</li>
                        <li>Se o botão não aparecer, abra o menu de três pontos do Chrome.</li>
                        <li>Escolha <strong>Instalar aplicativo</strong> ou <strong>Instalar e criar atalho</strong>, conforme o texto exibido no aparelho.</li>
                        <li>Siga a confirmação do Android. O ícone ficará na tela inicial e na lista de aplicativos.</li>
                    </ol>
                    <p>Para remover, abra <strong>Configurações → Apps</strong>, localize Escala Fácil e escolha <strong>Desinstalar</strong>. Consulte também a <a href="https://support.google.com/chrome/answer/9658361?co=GENIE.Platform%3DAndroid&amp;hl=pt-BR" target="_blank" rel="noopener noreferrer">orientação oficial do Google Chrome</a>.</p>
                </div>
            </details>
            <details>
                <summary>Instalar no iPhone ou iPad com Safari</summary>
                <div>
                    <ol>
                        <li>Abra o endereço do Escala Fácil no <strong>Safari</strong>.</li>
                        <li>Toque no botão de menu da página e depois em <strong>Compartilhar</strong>. Em algumas versões, o botão Compartilhar já aparece diretamente na barra.</li>
                        <li>Role as ações e selecione <strong>Adicionar à Tela de Início</strong>.</li>
                        <li>Quando a opção estiver disponível, mantenha <strong>Abrir como App</strong> ativada.</li>
                        <li>Toque em <strong>Adicionar</strong>. O ícone será incluído na Tela de Início.</li>
                    </ol>
                    <p>Se a ação não estiver na lista, use <strong>Editar Ações</strong> para adicioná-la. Para remover, mantenha o ícone pressionado e escolha a opção de remover aplicativo. Consulte também o <a href="https://support.apple.com/guide/iphone/open-as-web-app-iphea86e5236/ios" target="_blank" rel="noopener noreferrer">manual oficial da Apple</a>.</p>
                </div>
            </details>
            <details>
                <summary>Instalar no Windows com Microsoft Edge</summary>
                <div>
                    <ol>
                        <li>Abra o endereço do Escala Fácil no <strong>Microsoft Edge</strong>.</li>
                        <li>Use o ícone de instalação que aparece no lado direito da barra de endereços e confirme.</li>
                        <li>Se o ícone não aparecer, abra <strong>Configurações e mais (…)</strong>.</li>
                        <li>Acesse <strong>Mais ferramentas → Aplicativos → Instalar este site como um aplicativo</strong>.</li>
                        <li>Confirme o nome Escala Fácil e conclua a instalação.</li>
                        <li>Se desejar, fixe o aplicativo na barra de tarefas ou no menu Iniciar.</li>
                    </ol>
                    <p>Os aplicativos instalados podem ser gerenciados em <strong>edge://apps</strong>. Consulte também a <a href="https://support.microsoft.com/pt-br/edge/install-manage-or-uninstall-apps-in-microsoft-edge" target="_blank" rel="noopener noreferrer">orientação oficial do Microsoft Edge</a>.</p>
                </div>
            </details>
            <details>
                <summary>Atualizações, conexão e segurança</summary>
                <div>
                    <ul>
                        <li>O aplicativo recebe as atualizações do sistema automaticamente ao ser aberto com conexão.</li>
                        <li>Sem internet, uma tela informa que não foi possível acessar o serviço e oferece <strong>Tentar novamente</strong>.</li>
                        <li>Escalas, usuários e movimentações não são armazenados para leitura offline, evitando a exibição de informações antigas ou sensíveis.</li>
                        <li>É necessário estar conectado para entrar, consultar dados atualizados, solicitar trocas, informar ausências ou tomar decisões.</li>
                        <li>A instalação não altera seu perfil nem suas permissões; use o mesmo e-mail e senha do acesso pelo navegador.</li>
                    </ul>
                </div>
            </details>
        </div>
    </section>

    <section id="historico-versoes" class="help-section mt-8 scroll-mt-6" aria-labelledby="versions-title">
        <div class="help-section-heading">
            <span>08</span>
            <div><p class="eyebrow">Controle de versão</p><h2 id="versions-title">Histórico de versões</h2></div>
        </div>
        <p class="manual-section-intro">A versão mais recente aparece primeiro. Cada lançamento resume as mudanças visíveis para os usuários.</p>
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
