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
        <a href="#relatar-problema"><x-nav-icon name="flag" class="size-5" /><span>Relatar problema</span></a>
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
                        <li>Defina o horário inicial e o final. Para 24h, use horários iguais e informe a data de término no dia seguinte.</li>
                        <li>Sem data de término, um horário final menor que o inicial termina no dia seguinte. 07:00–07:01 no mesmo dia representa 1 minuto.</li>
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
                        <li>O modelo inclui data de término e duração em minutos. Para 24h, informe a data do dia seguinte e os mesmos horários. Se alterar datas ou horários, atualize a duração calculada ou deixe essa coluna vazia. Arquivos antigos de seis colunas continuam aceitos.</li>
                        <li>Use <strong>Exportar</strong> para baixar os plantões do mês exibido e aproveitá-los como base de edição.</li>
                        <li>Envie o arquivo em <strong>Importar</strong>: linhas duplicadas são ignoradas, e qualquer erro em uma linha cancela toda a importação com o motivo indicado.</li>
                    </ol>
                    <h4>Copiar escala de outro mês</h4>
                    <ol>
                        <li>Use <strong>Copiar escala</strong> em Escalas e escolha o mês de origem, o mês de destino e o mapeamento: mesmo dia do mês ou mesmo dia da semana.</li>
                        <li>Confira a <strong>prévia</strong>: quantidades, datas, setores, categorias, profissionais e conflitos. Dias inexistentes, duplicatas, choques de horário e profissionais incompatíveis ficam bloqueados com o motivo.</li>
                        <li>Desmarque itens para excluí-los da cópia e confirme. Trocas, ausências, notificações e comprovantes nunca são copiados, e plantões idênticos existentes nunca são sobrescritos.</li>
                        <li>Ao final, o relatório informa quantos plantões foram criados e quantos foram ignorados.</li>
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
                        <li>Abra <strong>Relatórios</strong> e selecione o mês, o setor e a categoria. Todos os números da tela passam a refletir os filtros; use <strong>Limpar filtros</strong> para voltar à visão completa.</li>
                        <li>Leia o quadro de resumo: <strong>Cobertura</strong> mostra o percentual de plantões com profissional; <strong>Descobertos</strong> conta os sem profissional; <strong>Trocas aprovadas</strong> soma as criadas no mês; <strong>Ausências</strong> soma as justificativas de plantões do mês.</li>
                        <li>Confira <strong>Plantões por setor</strong> e <strong>por categoria</strong> para localizar onde faltou cobertura.</li>
                        <li>Em <strong>Plantões por profissional</strong>, cada linha mostra plantões, trocas aprovadas no formato <strong>X de 4</strong> (a cota mensal) e ausências. Use essa tabela para conversas de alinhamento e para antecipar estouro de cota.</li>
                        <li>Em <strong>Trocas criadas no mês</strong>, acompanhe solicitante, substituto e situação de cada pedido.</li>
                        <li>Use <strong>Exportar PDF</strong> para guardar ou compartilhar o fechamento com os mesmos filtros da tela.</li>
                        <li>Use <strong>PDF para afixar</strong> para imprimir a escala do mês com legenda, trocas destacadas (● Troca) e linhas de assinatura da gerência e do responsável.</li>
                    </ol>
                </div>
            </details>
            <details id="gerenciar-trocas">
                <summary>7. Aprovar ou rejeitar trocas</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Trocas</strong> na sidebar. A fila gerencial exibe exclusivamente pedidos já aceitos pelo substituto.</li>
                        <li>Confira a <strong>visão geral da troca</strong>: o dia sendo substituído e o dia que vai substituir. Em trocas mútuas com contrapartida, o quadro detalha quem assume cada data e a faixa de horário completa (ex.: <em>Profissional A vai para o dia tal e Profissional B vai para o dia tal</em>).</li>
                        <li>Observe o selo <strong>Dobra</strong>: ele sinaliza quando a assunção do plantão faz o substituto emendar turnos consecutivos, permitindo autorizar com plena consciência.</li>
                        <li>Para <strong>Aprovar</strong>: selecione o botão, confira a movimentação detalhada no modal e confirme. O sistema revalida as cotas e transfere a titularidade dos plantões imediatamente. Um comprovante detalhado com botão de impressão é disponibilizado.</li>
                        <li>Para <strong>Rejeitar</strong>: selecione o botão e preencha obrigatoriamente o campo de <strong>justificativa</strong> no modal. A recusa encerra o pedido sem alterar a escala, e o motivo é enviado aos dois profissionais com o comprovante.</li>
                        <li>Guarde o código de referência do comprovante para fins de auditoria quando necessário.</li>
                    </ol>
                    <p>A decisão notifica automaticamente o solicitante e o substituto. A aprovação revalida a compatibilidade de setor/categoria e a cota mensal antes de efetivar qualquer alteração.</p>
                    <p>Para corrigir uma necessidade operacional futura, use <strong>Desfazer troca</strong> na lista de trocas aprovadas: os plantões voltam aos responsáveis originais, a cota mensal é liberada e ambos recebem comprovante de cancelamento. Apenas plantões futuros ainda sob titularidade do substituto e sem pendências podem ser desfeitos.</p>
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
                <summary>Consultar relatórios</summary>
                <div>
                    <ol>
                        <li>Abra <strong>Relatórios</strong> na sidebar e selecione o mês desejado.</li>
                        <li>Use os filtros de setor e categoria para focar em parte da escala; o resumo, as tabelas e as trocas acompanham os filtros.</li>
                        <li>No quadro de resumo, acompanhe a cobertura do mês, os plantões descobertos, as trocas aprovadas e as ausências.</li>
                        <li>Em <strong>Plantões por profissional</strong>, localize sua linha para ver seus plantões, suas trocas aprovadas (<strong>X de 4</strong>) e suas ausências no mês.</li>
                        <li>Use <strong>Exportar PDF</strong> para salvar o relatório ou <strong>PDF para afixar</strong> para imprimir a escala do mês.</li>
                    </ol>
                    <p>A consulta é somente leitura: relatórios não permitem criar, editar ou excluir registros.</p>
                </div>
            </details>
            <details id="solicitar-troca">
                <summary>Solicitar uma troca</summary>
                <div>
                    <ol>
                        <li>Localize um plantão futuro atribuído a você na página <strong>Minha escala</strong>.</li>
                        <li>Selecione o botão <strong>Solicitar troca</strong>.</li>
                        <li>Escolha um colega elegível no campo <strong>Substituto</strong> (o sistema lista apenas profissionais do mesmo setor e categoria que ainda não atingiram o limite mensal).</li>
                        <li><strong>Troca mútua (contrapartida):</strong> se combinou de cobrir um plantão do colega em retorno, selecione o plantão dele no campo <em>Plantão de contrapartida</em>. Caso deseje apenas transferir o seu plantão sem assumir outro, deixe o campo em branco (troca simples).</li>
                        <li>Selecione <strong>Revisar solicitação</strong> para abrir o modal de conferência.</li>
                        <li>Confira data, horário, setor, categoria e o plantão de contrapartida (se houver) e confirme em <strong>Confirmar envio</strong>.</li>
                        <li>Confira o aviso de sucesso e guarde a referência do comprovante gerado.</li>
                    </ol>
                    <p>O pedido passa para o status <strong>Aguardando profissional</strong>. O colega recebe notificação no sistema, o ícone do aplicativo PWA instalado atualiza o crachá com o número de avisos (badge) e uma notificação é enviada no aparelho celular.</p>
                </div>
            </details>
            <details id="responder-troca">
                <summary>Responder a um pedido recebido</summary>
                <div>
                    <ol>
                        <li>Abra a notificação recebida ou consulte a aba de pedidos recebidos em <strong>Minha escala</strong>.</li>
                        <li>Confira data, horário, setor e se há um plantão seu de contrapartida indicado no pedido.</li>
                        <li>Se o plantão recebido for contíguo (emendar turno) com outro horário seu, observe o alerta de <strong>Dobra</strong> para decidir com segurança.</li>
                        <li>Selecione <strong>Aceitar</strong> somente se tiver disponibilidade real para o compromisso.</li>
                        <li>Revise todos os dados e a consequência no modal e confirme o aceite.</li>
                        <li>Após o aceite, o status avança para <strong>Aguardando gerência</strong>, e a gerência é notificada para análise.</li>
                        <li>Aguarde a decisão: a escala oficial permanece inalterada até que o gestor conclua a aprovação. Uma notificação com o comprovante final (aprovado ou com a justificativa de recusa) será enviada a você.</li>
                    </ol>
                    <p>O aceite do substituto não muda a titularidade do plantão imediatamente; a transferência só é efetivada após a aprovação da gerência.</p>
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

        <div class="manual-callout mt-6" role="note">
            <strong>Como funciona a troca de escala no Escala Fácil</strong>
            <p>O módulo de trocas substitui acordos informais por um fluxo formal, auditável e seguro em 3 etapas (Solicitação → Aceite → Decisão Gerencial). O sistema suporta trocas simples e trocas mútuas com contrapartida, valida a compatibilidade de setor e categoria, controla o limite mensal de quatro trocas por profissional, alerta dobras de plantão e exige justificativa em caso de rejeição pela gerência.</p>
        </div>

        <div class="help-accordion mt-6">
            <details id="modalidades-troca" open>
                <summary>1. Modalidades de troca: simples e mútua (com contrapartida)</summary>
                <div>
                    <p>O Escala Fácil oferece duas formas de solicitar uma troca de plantão:</p>
                    <ul>
                        <li><strong>Troca Simples (1 via):</strong> o solicitante transfere a titularidade do seu plantão para o colega substituto, sem assumir nenhum plantão em contrapartida. Útil quando um profissional precisa se ausentar e o colega apenas assume o turno.</li>
                        <li><strong>Troca Mútua (2 vias / com contrapartida):</strong> o solicitante transfere o seu plantão para o colega E seleciona qual plantão futuro do colega irá cobrir em retorno. Quando a gerência aprova, ambos os plantões mudam de responsável simultaneamente na escala oficial.</li>
                    </ul>
                    <p>Em ambas as modalidades, a troca só altera a escala física após o aceite do colega e a aprovação formal da gerência.</p>
                </div>
            </details>

            <details id="regras-troca">
                <summary>2. Regras obrigatórias e condições de elegibilidade</summary>
                <div>
                    <p>Para manter a escala equilibrada e juridicamente segura, o sistema impõe quatro regras inegociáveis:</p>
                    <ol>
                        <li><strong>Compatibilidade estrita de setor e categoria:</strong> solicitante e substituto devem pertencer à mesma categoria profissional (ex.: Médico Clínico, Enfermeiro) e estar vinculados ao mesmo setor de atuação (ex.: UTI, Pronto Atendimento). O sistema impede a escolha de profissionais incompatíveis.</li>
                        <li><strong>Limite mensal de quatro trocas aprovadas:</strong> cada profissional pode participar de até <strong>quatro trocas aprovadas por mês</strong>, somando tanto as solicitações criadas quanto as substituições assumidas. A 5ª participação é bloqueada no envio, no aceite e na aprovação.</li>
                        <li><strong>Plantão elegível e futuro:</strong> a troca só pode ser iniciada sobre plantões futuros atribuídos ao profissional, com status <em>Agendado</em>, sem registro de ausência e sem outro pedido de troca pendente.</li>
                        <li><strong>Revalidação automática:</strong> as regras de compatibilidade e o saldo da cota mensal são rechecados pelo sistema em tempo real no momento do aceite do substituto e na decisão do gerente.</li>
                    </ol>
                </div>
            </details>

            <details id="passo-solicitante">
                <summary>3. Etapa 1 — Como solicitar uma troca (Solicitante)</summary>
                <div>
                    <ol>
                        <li>Acesse <strong>Minha escala</strong> no menu lateral e navegue até o mês do plantão que deseja trocar.</li>
                        <li>No cartão do plantão desejado, clique no botão <strong>Solicitar troca</strong>.</li>
                        <li>No campo <strong>Substituto</strong>, escolha o colega na lista. Somente aparecem profissionais compatíveis (mesmo setor e categoria) e que ainda possuem cota disponível no mês.</li>
                        <li><em>(Opcional para troca mútua)</em> No campo <strong>Plantão de contrapartida</strong>, selecione o plantão futuro do colega que você assumirá em troca. Se quiser apenas repassar o seu plantão, deixe a opção em branco.</li>
                        <li>Clique em <strong>Revisar solicitação</strong> para abrir o modal de conferência.</li>
                        <li>Verifique com atenção as datas, horários e os dois profissionais envolvidos. Clique em <strong>Confirmar envio</strong>.</li>
                        <li>Um comprovante imediato com código único de referência é gerado, e o pedido assume o status <strong>Aguardando profissional</strong>. O substituto recebe aviso no aplicativo, no celular e no badge do ícone do PWA.</li>
                    </ol>
                </div>
            </details>

            <details id="passo-substituto">
                <summary>4. Etapa 2 — Como o substituto responde e o alerta de dobra</summary>
                <div>
                    <ol>
                        <li>O substituto é avisado por notificação e localiza o pedido na aba de pedidos recebidos em <strong>Minha escala</strong> ou na <strong>Central de Notificações</strong>.</li>
                        <li>Abre o pedido para conferir a data e o horário do plantão oferecido e se há contrapartida de um plantão seu.</li>
                        <li><strong>Alerta inteligente de Dobra:</strong> se o plantão recebido for contíguo (emendar turno) com outro plantão já escalado para o substituto, o sistema exibe o aviso em destaque <strong>Dobra de plantão</strong>, permitindo avaliar o descanso antes de concordar.</li>
                        <li>Para concordar, o substituto clica em <strong>Aceitar</strong>, confere a confirmação no modal e confirma. Para recusar, clica em <strong>Recusar</strong>.</li>
                        <li>Ao aceitar, o status muda para <strong>Aguardando gerência</strong>. O solicitante e todos os gerentes da unidade recebem notificação e comprovante de aceite.</li>
                        <li><strong>Importante:</strong> o aceite ainda não transfere o plantão na escala. A escala só é alterada quando a gerência aprovar.</li>
                    </ol>
                </div>
            </details>

            <details id="passo-gerencia">
                <summary>5. Etapa 3 — Como a gerência aprova ou rejeita (Visão do Gestor)</summary>
                <div>
                    <ol>
                        <li>O gestor acessa o módulo <strong>Trocas</strong> na sidebar, onde visualiza a fila de pedidos pendentes de decisão.</li>
                        <li><strong>Visão geral da troca:</strong> o gestor vê exatamente o dia que está sendo substituído e o dia que vai substituir, com data, dia da semana e horários detalhados de ambos (ex.: <em>Profissional A vai para o dia tal e Profissional B vai para o dia tal</em>).</li>
                        <li>O gestor também visualiza o selo <strong>Dobra</strong> caso a troca crie turnos consecutivos sem descanso regulamentar para o substituto.</li>
                        <li><strong>Para Aprovar:</strong> clique em <strong>Aprovar</strong>, revise a movimentação completa no modal e confirme. O sistema valida as cotas, transfere a titularidade dos plantões na escala de forma atômica, gera o comprovante detalhado de movimentação com botão de impressão e notifica ambos os profissionais.</li>
                        <li><strong>Para Rejeitar:</strong> clique em <strong>Rejeitar</strong>. Um campo obrigatório de <strong>Justificativa</strong> é apresentado no modal. O gestor informa o motivo operacional da recusa e confirma. O pedido é encerrado sem alterar a escala, e a justificativa é enviada com o comprovante ao solicitante e ao substituto.</li>
                    </ol>
                </div>
            </details>

            <details id="desfazer-troca">
                <summary>6. Como desfazer uma troca aprovada (Reversão pela Gerência)</summary>
                <div>
                    <p>Caso ocorra um imprevisto operacional ou erro de agendamento em um plantão futuro cuja troca já havia sido aprovada, a gerência pode revertê-la:</p>
                    <ol>
                        <li>No módulo <strong>Trocas</strong>, localize a troca na lista de <em>Trocas aprovadas</em> (plantões futuros).</li>
                        <li>Clique em <strong>Desfazer troca</strong> e confira no modal o resumo dos profissionais e plantões que serão devolvidos.</li>
                        <li>Ao confirmar, o plantão original retorna para o solicitante (e o plantão de contrapartida, se houver, retorna para o substituto).</li>
                        <li>A cota mensal consumida (1 das 4 participações permitidas) é liberada e devolvida automaticamente a ambos os profissionais.</li>
                        <li>Um comprovante de cancelamento da troca é gerado e registrado na Central de Notificações dos profissionais envolvidos.</li>
                    </ol>
                    <p><em>Condição:</em> apenas plantões com data futura que ainda estejam sob posse do substituto e que não possuam ausência ou nova troca em andamento podem ser desfeitos.</p>
                </div>
            </details>

            <details id="sinalizacao-calendario">
                <summary>7. Destaque visual no Calendário Mensal e no PDF para afixar</summary>
                <div>
                    <p>Para garantir que toda a equipe e a gerência identifiquem facilmente os dias em que houve troca:</p>
                    <ul>
                        <li><strong>Calendário Mensal online:</strong> os dias que contêm plantões transferidos por troca aprovada são destacados em <strong>tom laranja</strong> com fundo suave, borda temática e o símbolo <strong>⇄</strong>.</li>
                        <li><strong>Cartões de plantão do dia:</strong> ao clicar no dia do calendário, o plantão que sofreu troca exibe o selo em destaque <strong>⇄ Troca realizada</strong>.</li>
                        <li><strong>Legenda do calendário:</strong> o item explicativo <em>⇄ Troca realizada</em> permanece visível na barra de legendas do calendário.</li>
                        <li><strong>PDF para afixar (Escala do mês):</strong> a escala mensal para mural exibe o marcador especial <strong>● Troca</strong> ao lado do nome do profissional substituto, garantindo conferência física clara no setor.</li>
                    </ul>
                </div>
            </details>

            <details id="comprovantes-notificacoes">
                <summary>8. Comprovantes com impressão, badges no PWA e notificações no celular</summary>
                <div>
                    <p>O fluxo de trocas conta com auditoria e notificações integradas para manter todos os participantes alinhados:</p>
                    <ul>
                        <li><strong>Comprovante oficial:</strong> toda solicitação, aceite, aprovação, rejeição ou reversão gera um comprovante com código de referência único (ex.: <em>TRC-2026-XXXX</em>), dados imutáveis do momento da ação e identificação do responsável.</li>
                        <li><strong>Botão Imprimir comprovante:</strong> dentro do modal do comprovante, o botão de impressão permite imprimir diretamente ou salvar como PDF para arquivo pessoal ou apresentação de documentos.</li>
                        <li><strong>Badges no ícone do PWA instalado:</strong> usuários que instalam o aplicativo no celular ou desktop visualizam a contagem de avisos e trocas pendentes como um número diretamente sobre o ícone do aplicativo (App Badging API).</li>
                        <li><strong>Avisos no aparelho celular:</strong> com as notificações do dispositivo ativadas na Central de Notificações, o usuário recebe avisos sonoros e visuais no celular quando uma troca for solicitada, aceita, aprovada ou rejeitada.</li>
                    </ul>
                </div>
            </details>
        </div>

        <div class="help-content-grid mt-6">
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

    <section id="relatar-problema" class="help-section mt-8 scroll-mt-6" aria-labelledby="report-issue-title">
        <div class="help-section-heading">
            <span>07</span>
            <div><p class="eyebrow">Suporte</p><h2 id="report-issue-title">Relatar problema</h2></div>
        </div>
        <p class="manual-section-intro">Encontrou um erro ou comportamento inesperado? Descreva o problema abaixo. A gerência recebe o relato com número de protocolo. Não informe senhas nos campos do formulário.</p>
        <livewire:support-report-form />
    </section>

    <section id="instalar-aplicativo" class="help-section mt-8 scroll-mt-6" aria-labelledby="install-app-title">
        <div class="help-section-heading">
            <span>08</span>
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
            <span>09</span>
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
