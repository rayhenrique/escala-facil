# Histórico de versões — Escala Fácil

Este arquivo registra todas as versões funcionais da aplicação. O projeto usa versionamento semântico no formato `MAIOR.MENOR.CORREÇÃO`:

- **MAIOR:** mudança incompatível ou nova geração do produto;
- **MENOR:** funcionalidade nova compatível com a versão anterior;
- **CORREÇÃO:** ajuste compatível, sem nova funcionalidade.

## 1.32.0 — 11/10/2026

### Indicadores operacionais por setor

- Os novos indicadores ajudam a acompanhar a cobertura, as trocas e as ausências de cada setor.

## 1.31.0 — 11/10/2026

### Ativação de contas por convite

- Gerentes agora podem convidar profissionais, que recebem um link para criar sua própria senha e acessar o aplicativo.

## 1.30.0 — 10/10/2026

### Alertas preventivos da escala

- O sistema agora avisa quando uma alteração pode causar conflito de horários ou incompatibilidade na escala.

## 1.29.0 — 10/10/2026

### Histórico de alterações operacionais

- Gerentes agora podem consultar quem alterou um plantão, quando a mudança aconteceu e o que foi modificado.

## 1.28.0 — 10/10/2026

### Pesquisa e filtros operacionais

- Agora você encontra plantões e pedidos de troca com mais facilidade usando novos filtros.

## 1.27.0 — 10/10/2026

### Realocação assistida de plantões

- Ficou mais fácil encontrar profissionais disponíveis e preencher plantões descobertos.

## 1.26.0 — 10/10/2026

### Análise de trocas no painel

- Gerentes agora podem analisar e decidir pedidos de troca diretamente pelo painel.

## 1.25.0 — 10/10/2026

### Acesso direto aos registros

- Agora você pode abrir diretamente um plantão ou pedido de troca, sem precisar procurá-lo novamente.

## 1.24.0 — 10/10/2026

### Pendências organizadas por prioridade

- As tarefas que precisam da sua atenção agora aparecem organizadas por prioridade, sem informações repetidas.

## 1.23.1 — 10/10/2026

### Acesso mais fácil pelo celular

- Formulários mais claros no celular, com mensagens de erro e foco nos campos que precisam de revisão.
- Instalação opcional com menos destaque e orientação para adicionar o aplicativo no iPhone e iPad.

## 1.23.0 — 10/10/2026

### Apresentação mais clara do aplicativo

- Apresentação mais clara dos recursos, do fluxo de troca e do acesso institucional.
- Prévia com componentes do aplicativo e navegação mais fácil no computador e no celular.

## 1.22.1 — 10/10/2026

### Versão correta em todas as telas

- A versão do aplicativo agora aparece corretamente em todas as telas.

## 1.22.0 — 10/10/2026

### Plantões de 24h e horários mais claros

- Plantões de 24h podem ser cadastrados com o mesmo horário de início e fim e término no dia seguinte.
- Calendário, painel, relatórios, trocas e PDFs mostram a data de término e a duração do plantão.
- Planilhas antigas continuam aceitas e os dados já cadastrados são preservados; 07:00 a 07:01 continua representando 1 minuto.
- Verificação de conflitos e avisos de dobra corrigidos para plantões entre dias e meses.

## 1.21.1 — 10/10/2026

### Seções da página aparecem corretamente

- Conteúdo visível mesmo quando as animações não carregam ou o JavaScript está desativado.
- Rolagem e recarregamento da página corrigidos no computador e no celular, respeitando movimento reduzido.

## 1.21.0 — 10/10/2026

### Duplicação assistida de escalas mensais

- Ação Copiar escala em Escalas com mês de origem, destino e mapeamento por dia ou dia da semana.
- Prévia com quantidades, datas, profissionais e conflitos: datas inexistentes, duplicatas, choques de horário e incompatibilidades.
- Revisão com exclusão de itens, criação transacional sem sobrescrita e relatório de criados e ignorados.
- Trocas, ausências, notificações e comprovantes nunca copiados; cobertura crítica por testes.

## 1.20.0 — 10/10/2026

### Dashboard com foco em 72h e calendário com ações contextuais

- Nova seção Próximas 72 horas no painel com próximo descoberto, pendência mais antiga e atalhos, sem remover indicadores e listas existentes.
- Ações contextuais no dia selecionado: alocar profissional, analisar troca ou abrir escala, somente para gerentes.
- Botão Hoje, indicador de carregamento e filtros preservados no calendário mobile e desktop.
- Índices em plantões e trocas para as leituras quentes e limite de consultas coberto por teste.

## 1.19.1 — 10/10/2026

### Correção de vulnerabilidades npm

- concurrently 9.2.4 para 9.2.5 com shell-quote 1.12.0, corrigindo injeção de comando crítica.
- postcss 8.5.28 para 8.5.29 com source-map-js 1.2.2, corrigindo negação de serviço de alta severidade.
- Correções obtidas por atualização semver compatível, sem overrides e sem npm audit fix --force.
- npm audit sem vulnerabilidades em todos os níveis; build, suíte de testes e Pint validados.

## 1.19.0 — 10/10/2026

### Landing unificada ao design system

- Paleta da landing migrada para ink, muted, primary, coverage, canvas e line, com botões em rounded-lg e pílulas só para status.
- Removidos gradientes, vidro, blobs com blur, sombras customizadas e deslocamentos no hover; bloco de conversão na faixa escura primary-strong.
- Navegação pública sem links para rotas autenticadas, menu móvel por teclado e simulação estática com prefers-reduced-motion.
- DESIGN.md e UX-CONTRACT.md atualizados com os padrões da landing unificada.

## 1.18.1 — 10/10/2026

### Acessibilidade e navegação da landing page

- Lenis, parallax e animações desativados com prefers-reduced-motion, mantendo todo o conteúdo visível.
- Links públicos de ajuda removidos da navegação e redirecionados para seções públicas da página.
- Menu mobile com rótulo dinâmico, controle de expansão e fechamento por Escape.
- Microtextos com contraste reforçado e mockup com largura fluida sem overflow no celular.
- Testes de regressão para navegação pública e acessibilidade.

## 1.18.0 — 10/10/2026

### Observabilidade e relato de problemas

- Integração opcional com Sentry via SENTRY_DSN, com expurgo de senhas, tokens e dados sensíveis e contexto de versão, rota, perfil e erro.
- Captura de erros JavaScript não tratados com envio limitado e anexação ao relato local, sem quebrar a aplicação.
- Novo Relatar problema na Ajuda com protocolo, anexo validado e lista restrita à gerência.
- Cobertura de permissões, validação, persistência e privacidade dos relatos.

## 1.17.1 — 10/10/2026

### Deploy de produção reforçado com backup e rollback seguro

- Fase de pré-voo sem alterações: pré-requisitos, espaço em disco, .env, fast-forward e composer validate --strict antes da manutenção.
- Backup automático do banco antes das migrations, fora do repositório, com retenção dos 10 mais recentes e permissões 700/600.
- Rollback seguro de código e assets em falhas antes das migrations; migrations nunca revertidas automaticamente.
- Verificações de fumaça após reabrir o sistema e diagnóstico com procedimento de recuperação em deploy.md.
- Cobertura automatizada do script em tests/Feature/DeployScriptTest.php.

## 1.17.0 — 10/10/2026

### Integração contínua no GitHub Actions

- Novo workflow .github/workflows/ci.yml executado em push e pull request, sem deploy automático.
- Validações encadeadas: composer validate --strict, composer audit --locked, php artisan test, vendor/bin/pint --test, npm ci e npm run build.
- Matriz de banco SQLite + MySQL 8.0 com PHP 8.5 e Node 24, cache seguro de dependências e .env.testing sem credenciais reais.
- Documentação da proteção da branch main exigindo CI aprovado.

## 1.16.4 — 10/10/2026

### Versionamento centralizado com release:prepare

- Novo comando php artisan release:prepare X.Y.Z com validação SemVer e proteção contra regressão e duplicação.
- Sincronização automática de config/version.php, .env.example, README.md, deploy.md, versoes.md e public/sw.js.
- Cache do service worker versionado automaticamente para invalidação correta após cada atualização, sem manutenção manual.
- Estratégia segura de cache autenticado preservada, com cobertura de testes para o comando e consistência entre arquivos.
- Arquivo .env nunca alterado durante a preparação local.

## 1.16.3 — 10/10/2026

### Comprovantes históricos imutáveis e status em tempo de execução

- **Sem escritas no `render()` da Central:** A Central de Notificações resolve o estado atual apenas para leitura em tela, sem atualizar o banco durante a renderização.
- **Histórico preservado:** Solicitação, aceite, aprovação, rejeição e reversão mantêm seus eventos e comprovantes originais; decisões não reescrevem notificações anteriores.
- **Situação atual visível sem alterar o histórico:** Cards com status divergente exibem o aviso `Situação atual da troca` com responsável e justificativa, mantendo o comprovante original abaixo.
- **Registros existentes intactos:** Nenhuma migração destrutiva foi necessária; o comando `php artisan app:sync-swap-notifications` apenas verifica e preserva os registros sem reescrever comprovantes.
- **Consistência entre superfícies:** Notificações, calendário, relatórios e PDFs passam a ler a mesma fonte autoritativa (`ShiftSwap`), com cobertura de testes para os cinco eventos.

## 1.16.2 — 10/10/2026

### Correção dos links de notificações PWA

- **Destino das notificações do dispositivo corrigido:** O aviso criado em `resources/js/app.js` agora aponta para `/notifications` em vez da rota inexistente `/notificacoes`.
- **Clique na notificação abre a página correta:** O `public/sw.js` normaliza o destino para URL absoluta, reaproveita a janela aberta ou abre uma nova, funcionando inclusive com o PWA instalado.
- **Atualização garantida em instalações existentes:** Cache do service worker atualizado para `escala-facil-static-v1.16.2`.
- **Regressão coberta por testes:** Nova suíte valida rota, Central de Notificações, `target_url` dos payloads, bundle JS, service worker e atalho do manifesto, preservando badge e notificações.

## 1.16.1 — 03/10/2026

### Sincronização em tempo real de notificações de troca e comprovantes

- **Sincronização em tempo real na Central de Notificações:** As notificações de trocas agora têm seu status, comprovante e justificativas atualizados dinamicamente com base no estado real do banco de dados ao serem visualizadas.
- **Atualização ativa nas decisões da gerência:** Ao aprovar, rejeitar ou reverter uma troca, todas as notificações preexistentes daquela troca são atualizadas imediatamente no banco de dados.
- **Harmonização completa entre tela e PDF:** O card do comprovante exibido em notificações reflete exatamente os mesmos dados do PDF oficial (situação Rejeitada/Aprovada, responsável pela decisão e justificativa detalhada).
- **Banner informativo na movimentação da escala:** O comprovante exibe um alerta visual claro informando que a solicitação foi rejeitada pela gerência e que a escala original foi preservada.
- **Comando artisan de manutenção:** Disponibilizado o comando `php artisan app:sync-swap-notifications` para sincronização em massa de notificações existentes.

## 1.16.0 — 03/10/2026

### Submódulos de relatórios, impressão isolada e salvamento de comprovante em PDF

- **Submódulos no módulo Relatórios:** Criação de abas e visões analíticas especializadas: Visão Consolidada, Cobertura e Indicadores, Por Profissional, Trocas de Plantão, Ausências e Justificativas, e Escala de Mural.
- **Filtro e cota por profissional:** Busca instantânea por colaborador e visualização direta da cota de trocas aprovadas no mês com indicador de limite (`X de 4`).
- **Filtro de situação em trocas:** Tabela de auditoria com filtro por status (Aprovadas, Aguardando gerência, Aguardando profissional, Rejeitadas) e download direto de comprovantes.
- **Escala de mural integrada:** Visualização da grade diária de plantões e botão direto para download do PDF oficial de afixação (A4 horizontal).
- **Submenu no menu lateral:** Atalhos diretos para cada submódulo de relatórios na barra lateral de navegação.
- **Impressão isolada de comprovante de troca:** O botão "Imprimir" isola o comprovante selecionado via iframe oculto com cabeçalho oficial e assinaturas físicas, sem imprimir o restante da página.
- **Download de comprovante em PDF:** Botão dedicado "Salvar PDF" com rota segura `/swaps/{swap}/receipt/pdf` e template A4 oficial com dados dos plantões e histórico de decisões.
- **Rodapé dinâmico:** Link oficial para `kltecnologia.com` e versão dinâmica do sistema exibida no rodapé institucional.

## 1.15.0 — 02/10/2026

### Isolamento de setor para profissionais e iniciais no calendário

- **Isolamento de setor em todos os módulos:** O usuário com perfil profissional agora visualiza estritamente os plantões, relatórios e métricas pertencentes ao seu setor de vinculação.
- **Bloqueio rígido no calendário e relatórios:** Na visão do profissional, o seletor de setor permanece travado e desabilitado exibindo o nome de sua unidade de lotação, impedindo a visualização ou filtro de outros setores.
- **Segurança em relatórios e PDFs:** Exportações de relatório gerencial e escala para afixar solicitadas por profissionais contêm unicamente os dados do setor vinculado, prevenindo vazamento de informações mesmo se parâmetros de requisição forem alterados.
- **Monogramas visuais no calendário:** Identificação dos plantonistas por iniciais (duas letras) nas células do calendário mensal.

## 1.14.0 — 02/10/2026

### Landing page cinematográfica, privacidade de trocas e tooltips detalhadas

- **Landing page cinematográfica:** Implementação completa mobile-first em Blade, Tailwind CSS, Lenis (smooth scroll) e GSAP/ScrollTrigger com componentes modulares anônimos, animações de atrito vs fluidez e demonstração interativa.
- **Privacidade e restrição de trocas:** Usuários profissionais visualizam exclusivamente as suas próprias solicitações de troca (onde figuram como solicitante ou substituto) nos relatórios, extratos e agenda, enquanto gestores e administradores mantêm a visão global da escala.
- **Tooltips interativas de trocas:** Ao passar o mouse sobre o selo `⇄ Troca realizada`, surge um card flutuante indicando quem solicitou e quem assumiu o plantão, além de diferenciar trocas simples de permutas mútuas.
- **Identificação no calendário mensal:** O símbolo `⇄` nas células de dias com trocas aprovadas apresenta ao passar o mouse a lista de participantes que trocaram naquele dia.

## 1.13.0 — 02/10/2026

### Notificações PWA, comprovante detalhado e trocas destacadas

- Contador de notificações pendentes integrado ao ícone do aplicativo PWA instalado (App Badging API) e suporte a avisos no aparelho.
- Tela de aprovação exibe a troca geral com o dia que vai substituir e o dia que está sendo substituído, além de horários detalhados.
- Comprovante detalhado de movimentação da escala com resumo de para onde cada profissional foi e botão de impressão direta.
- Rejeição de troca com campo obrigatório de justificativa, registrado em banco e devolvido ao solicitante e ao substituto com comprovante.
- Destaque visual em tom laranja com símbolo e legenda no calendário mensal e nos cartões de plantão do dia para trocas aprovadas.

## 1.12.0 — 30/09/2026

### Relatórios compartilhados e manual detalhado

- Profissionais consultam relatórios, exportam o PDF gerencial e o PDF para afixar.
- Manual com passo a passo detalhado do fechamento do mês e da consulta de relatórios.
- Rotas gerenciais de relatórios seguem exclusivas da gerência.

## 1.11.0 — 30/09/2026

### Dobras, desfazer troca e PDF para afixar

- Aceite de troca avisa o substituto quando emenda (dobra) com outro plantão seu.
- Fila de aprovação exibe o selo Dobra para a gerência autorizar com consciência.
- Gerência pode desfazer trocas aprovadas futuras, com devolução do plantão, comprovante e liberação da cota.
- Novo PDF para afixar com grade mensal, legenda, trocas destacadas e assinaturas.

## 1.10.0 — 30/09/2026

### Cobertura de 24h no calendário

- Dias com 24 horas cobertas por plantões com profissional recebem o marcador ✓.
- Madrugadas de plantões noturnos contam para a cobertura do dia seguinte.
- Legenda do calendário e descrição acessível atualizadas.

## 1.9.0 — 30/09/2026

### Módulo Relatórios com exportação em PDF

- Novo item Relatórios no menu da gerência com resumo de cobertura, trocas e ausências do mês.
- Filtros de mês, setor e categoria aplicados a todos os números e tabelas.
- Plantões por setor, por categoria, por profissional (com cota de trocas e ausências) e trocas criadas no mês.
- Exportação em PDF com os mesmos filtros exibidos na tela.

## 1.8.0 — 30/09/2026

### Importação e exportação de plantões em XLSX

- Modelo padrão de planilha com os setores e categorias cadastrados para referência exata.
- Importação de plantões com validação por linha: qualquer erro cancela tudo e duplicatas são ignoradas.
- Exportação dos plantões do mês exibido, pronta para editar e reimportar.
- Profissional identificado pelo e-mail; plantão sem e-mail fica sem profissional alocado.

## 1.7.0 — 30/09/2026

### Filtros do calendário por setor e categoria

- Calendário mensal com filtros de setor e categoria para gerentes e profissionais.
- Contagens diárias e relação de plantões do dia passam a refletir os filtros ativos.
- Botão para limpar os filtros e mensagem específica quando nenhum plantão corresponde à seleção.

## 1.6.1 — 29/09/2026

### Plantões noturnos e robustez temporal

- Permitido o cadastro de plantões noturnos que atravessam a meia-noite (ex.: 19h às 07h) no módulo de escalas da gerência.
- Validação do horário final atualizada para garantir horários diferentes em vez de exigir que seja posterior no mesmo dia civil.
- Eliminada a fragilidade temporal em testes que utilizam projeção de datas no encerramento do mês.
- Aviso de limite de cota mensal ajustado para informar o mês corrente caso o plantonista consulte outro período.
- Comando interativo de criação de gerente reforçado com limpeza de variáveis sensíveis em memória.

## 1.6.0 — 27/09/2026

### Confirmações claras e feedback imediato

- Exclusões de categorias, setores, usuários e plantões agora abrem um modal com o registro selecionado e a consequência da ação.
- Solicitação e aceite de trocas, decisão da gerência e registro de ausência possuem uma etapa de revisão antes de alterar dados.
- A leitura em massa da Central de Notificações exige confirmação e informa quantos avisos terão o estado alterado.
- Mensagens de sucesso e erro foram padronizadas em avisos flutuantes, acessíveis e adaptados a telas pequenas.
- Os modais críticos oferecem foco inicial, fechamento por Escape, bloqueio durante o processamento e texto explícito nos botões.
- O manual, o contrato de experiência e os testes automatizados foram atualizados para os novos fluxos.

## 1.5.0 — 27/09/2026

### Aplicativo instalável e manual completo

- O Escala Fácil agora possui manifesto, ícones e service worker para instalação como PWA no Android, iPhone, iPad e Windows.
- Adicionado botão de instalação contextual na tela de acesso e na sidebar quando o navegador disponibiliza a ação.
- A tela offline informa a indisponibilidade da conexão sem armazenar páginas autenticadas, escalas ou dados de usuários.
- O manual apresenta instruções detalhadas de instalação no Google Chrome para Android, Safari para iOS/iPadOS e Microsoft Edge para Windows.
- A Central de Ajuda ganhou uma sequência inicial explícita: categorias, setores, usuários, plantões, calendário e operação diária.
- O manual da gerência agora detalha criação, edição, exclusão, bloqueios de histórico, rotina diária, decisões de troca e comprovantes.
- O manual do profissional detalha consulta da própria escala e do calendário completo, solicitação e aceite de trocas, ausência e notificações.
- Os fluxos de troca e ausência informam participantes, destinatários das notificações, limite mensal e efeito de cada ação.
- Adicionadas referências de status, permissões, segurança e solução dos problemas mais comuns.
- A apresentação permanece mobile-first, com roteiro visual, atalhos e acordeões acessíveis.

## 1.4.0 — 27/09/2026

### Calendário compartilhado

- O perfil profissional agora possui o item **Calendário** na sidebar.
- Profissionais podem navegar pelos meses, selecionar um dia e consultar todos os plantões e plantonistas daquela data.
- A visualização informa horário, setor, categoria, situação de cobertura e plantões ainda sem profissional.
- Atalhos de criação, edição e gestão de plantões permanecem visíveis somente para gerentes.
- O calendário passou a ser um componente compartilhado com autorização específica de consulta.

## 1.3.2 — 26/09/2026

### Robustez operacional e gerente inicial

- Adicionado comando interativo para criar a primeira conta de gerente sem gravar a senha no código ou no histórico do terminal.
- Criado script de deploy que valida o Git antes de entrar em manutenção e interrompe o processo em caso de divergência ou arquivos alterados.
- Notificações de troca agora são persistidas na mesma transação das mudanças operacionais, evitando confirmações parciais.
- Reforçado o bloqueio concorrente do limite mensal de trocas durante a aprovação gerencial.
- Filtros mensais inválidos são normalizados antes das consultas.
- Remoção da massa fictícia também elimina notificações ligadas aos usuários de demonstração e preserva os dados reais.
- Seeders com credenciais conhecidas são bloqueados em produção sem autorização explícita.
- Formulários Livewire usam `POST` e CSRF como fallback seguro caso o JavaScript deixe de carregar.

## 1.3.1 — 26/09/2026

### Correção do acesso em produção

- Corrigido o login quando o Nginx do CloudPanel bloqueia a rota dinâmica do JavaScript do Livewire.
- Livewire passou a ser compilado dentro do bundle do Vite, eliminando a dependência da rota dinâmica `livewire.min.js`.
- O formulário de login agora usa `POST` como fallback, impedindo que e-mail e senha sejam enviados pela URL caso o JavaScript não carregue.
- Adicionado teste de regressão para validar o carregamento manual do Livewire e a proteção do formulário de acesso.

## 1.3.0 — 26/09/2026

### Notificações e comprovantes de troca

- Nova Central de Notificações disponível para gerentes e profissionais.
- Contador de notificações não lidas na sidebar, com atualização periódica.
- Aviso ao substituto quando uma solicitação de troca é criada.
- Aviso a todos os gerentes quando o substituto aceita e a troca aguarda aprovação.
- Avisos ao solicitante e ao substituto após aceite, aprovação ou rejeição.
- Comprovante imutável em cada evento com referência, plantão, setor, categoria, participantes, situação e responsável pela decisão.
- Comprovante imediato para o usuário que solicita, aceita, aprova ou rejeita a troca.
- Controles para marcar uma notificação ou todo o histórico como lido.
- Guia de produção `deploy.md` para Hostinger e CloudPanel, incluindo banco, SSL, cron, worker, atualização e backup.

## 1.2.0 — 26/09/2026

### Central de ajuda e novidades

- Novo módulo Ajuda com manual completo para gerentes e profissionais.
- Histórico de versões disponível dentro do sistema.
- Modal de novidades exibido uma vez por usuário em cada nova versão.
- Registro da última versão visualizada por usuário.
- README e processo de lançamento atualizados.
- Rodapé global com autoria da KL Tecnologia e versão do sistema.
- Publicação do código-fonte em repositório público no GitHub.
- Licença proprietária da KL Tecnologia destacada no repositório e nos metadados do projeto.

## 1.1.0 — 26/09/2026

### Calendário e gestão mais rápida

- Calendário mensal com todos os plantões e profissionais de cada dia.
- Sidebar recolhível no desktop com preferência persistida.
- Usuários reposicionado como último módulo da área gerencial.
- Formulários de Plantões, Usuários, Setores e Categorias em modais responsivos.
- Massa fictícia isolada com comandos para instalar, remover e recriar.

## 1.0.0 — 26/09/2026

### MVP operacional

- CRUD completo de usuários, setores e categorias.
- Gestão completa de plantões pela gerência.
- Interface totalmente em português do Brasil.
- Experiência mobile-first com cartões no celular e tabelas no desktop.
- Sidebar autenticada com ícones.
- Proteção do histórico operacional e das compatibilidades de escala.

## 0.4.0 — 26/09/2026

### Painéis e qualidade

- Dashboard gerencial com indicadores reais de cobertura.
- Alertas de plantões descobertos e trocas pendentes.
- Cartões e badges semânticos para os estados operacionais.
- Testes do limite mensal de trocas e da compatibilidade entre profissionais.

## 0.3.0 — 25/09/2026

### Fluxos de plantão

- Gestão de escalas e agenda mensal do profissional.
- Solicitação, aceite e aprovação gerencial de trocas.
- Transferência automática do plantão após aprovação.
- Registro de ausência justificada com anexo privado.

## 0.2.0 — 25/09/2026

### Domínio e acesso

- Models Eloquent, casts e relacionamentos do domínio.
- Autenticação e recuperação de senha.
- Redirecionamento por perfil.
- Middleware e Policies para gerentes e profissionais.

## 0.1.0 — 25/09/2026

### Fundação do Escala Fácil

- Projeto criado com Laravel, Livewire, Tailwind CSS e Breeze.
- Banco MySQL com InnoDB.
- Migrations de categorias, setores, usuários, plantões, trocas e ausências.
- Seeders iniciais para desenvolvimento.

## Processo para uma nova versão

1. Escolha o próximo número conforme o versionamento semântico.
2. Execute `php artisan release:prepare X.Y.Z --title="Título" --summary="Resumo" --highlight="Destaque"` para sincronizar `config/version.php`, `.env.example`, `README.md`, `deploy.md`, este arquivo e `public/sw.js` (o `.env` nunca é alterado).
3. Descreva mudanças concretas, impactos para o usuário e eventuais instruções de migração.
4. Execute migrations, testes, Pint, build e validação visual.
5. Ao entrar, cada usuário verá automaticamente o modal da nova versão até confirmar a leitura.
