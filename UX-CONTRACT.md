# Contrato de Experiência — Escala Fácil

Este documento registra o comportamento compartilhado das telas autenticadas. As regras de negócio permanecem definidas no `PRD.md`, no `MVP-SCOPE.md` e nas Policies, Form Requests e Actions do backend.

## Contexto

- **Público:** gestores de escala e profissionais plantonistas.
- **Idioma e formatos:** português do Brasil, datas em `dd/mm/aaaa` e horários em 24 horas.
- **Intervalos de plantões:** exibir duração e, quando atravessam a meia-noite, “dia seguinte” com a data de término. Plantões de 24h usam horários iguais com término explícito no dia seguinte. 07:00–07:01 no mesmo dia vale 1 minuto. Registros antigos mantêm sua interpretação; contrato e ambiguidades em `docs/shift-timing.md`.
- **Dispositivos:** celular como base para todos os fluxos; telas maiores ampliam a densidade da gestão sem alterar capacidades.
- **Acessibilidade:** controles com rótulos visíveis, foco perceptível, status com texto e cor, áreas interativas com pelo menos 40 px e respeito a `prefers-reduced-motion`.

## Mapa canônico de interface

| Capacidade | Responsável canônico | Variantes permitidas | Verificação |
| --- | --- | --- | --- |
| Navegação | sidebar em `resources/views/layouts/app.blade.php` | fixa e recolhível no desktop; gaveta no celular | rota ativa, persistência do toggle, ícones com texto e fechamento da gaveta |
| Campos e formulários | classes compartilhadas de `resources/css/app.css` + Form Requests | criar, editar, troca e ausência | validação próxima ao campo e testes Livewire |
| Acesso e recuperação | `layouts/guest` + componentes `auth/form`, `auth/password-field`, `auth/submit` + módulos Livewire Auth | login, solicitar recuperação e definir senha | campos nativos com autofill, erros associados, foco no primeiro inválido, carregamento, recuperação de falha e política de sessão preservada |
| Seleção | elemento HTML `select` com `field-input` | simples | rótulo, teclado e valor persistido |
| Data e período | inputs HTML `date`, `time` e `month` | data, hora e mês | locale visível e validação no servidor |
| Badge de status | `resources/views/components/status-badge.blade.php` | enums de plantão, troca e ausência | texto semântico e contraste |
| Cartão de plantão | `resources/views/components/shift-card.blade.php` | profissional, gerência e alerta | conteúdo equivalente em desktop e celular |
| Cartão de cadastro | classe `record-card` em `resources/css/app.css` | usuários, setores e categorias | conteúdo e ações equivalentes à tabela |
| Modal de cadastro | classes `modal-*` em `resources/css/app.css` | criar e editar plantões, usuários, setores e categorias | abertura por botão, foco inicial, validação, cancelamento e rolagem móvel |
| Modal de confirmação | `resources/views/components/confirmation-modal.blade.php` | exclusões, trocas, ausência e leitura em massa | registro e consequência visíveis, foco no cancelamento, Escape, autorização e bloqueio durante o processamento |
| Calendário mensal | `MonthlyCalendar` + classes `calendar-*` | consulta compartilhada, filtros de setor/categoria e ações gerenciais condicionais | navegação entre meses, botão Hoje, seleção de dia, filtros com limpeza, lista completa de plantões, ações contextuais por plantão só para gerentes, indicador de carregamento e ausência de ações sem permissão |
| Painel gerencial | `Manager\Dashboard` + `ManagerAttention` | indicadores globais acionáveis, fila única e filtros Hoje/3 dias/7 dias | cobertura antes de trocas, início antes de antiguidade, oito itens por página, URL e paginação consistentes, dias de calendário distintos das 72h corridas |
| Realocação assistida | `ShiftReallocation` + `ReallocatesShifts` + `ReallocateShiftAction` | painel, calendário e Escalas | cadastro compatível, conflitos reais, dobra, seleção/prévia/confirmar, snapshot revalidado, transação e histórico |
| Paginação operacional | `components/operational-pagination` | fila gerencial | Anterior/Próxima como botões nativos, limites desativados, página atual textual, foco e retorno ao título da fila |
| Navegação contextual | `OperationalNavigation` + `HasOperationalContext` + `components/context-navigation` | painel, calendário, escala, troca e relatório | ID validado e autorizado, registro destacado, foco no destino, Voltar por URL interna e filtros preservados inclusive em refresh e link compartilhado |
| Central de ajuda | `HelpCenter` | implantação inicial, rotinas por perfil, fluxos críticos, diagnóstico e versões | acesso pelos dois perfis, ordem inicial preservada, âncoras, acordeões e histórico completo |
| Aplicativo PWA | `manifest.webmanifest` + `sw.js` + ação `data-pwa-install` | Android, iOS/iPadOS e Windows | instalação nativa quando suportada, modo independente, ícones válidos e fallback offline sem cache autenticado |
| Página pública | `landing.blade.php` + componentes `landing/*` | seções com âncora, prévia e exemplo de troca | mesmos tokens e tipografia, cartão de plantão e badges compartilhados, dados ilustrativos, login como único destino autenticado, navegação por teclado e conteúdo legível sem JavaScript |
| Novidades da versão | `ReleaseAnnouncement` + `last_seen_version` | primeiro acesso após lançamento | modal único por versão, confirmação persistida e acesso ao manual |
| Notificações | `NotificationsCenter` + notificações em banco | solicitação, aceite, aprovação e rejeição | isolamento por usuário, contador de não lidas e controles de leitura |
| Comprovante de troca | `resources/views/components/swap-receipt.blade.php` | imediato e histórico | retrato do evento com referência, plantão e participantes |
| Feedback | `resources/views/components/flash-notifications.blade.php` | sucesso e erro | aviso flutuante, dispensável, sem alterar a geometria e com semântica de status ou alerta |
| Exclusão | modal de confirmação compartilhado | plantões, usuários, setores e categorias | registro identificado, consequência descrita, cancelamento e teste do fluxo |
| Importação e exportação | `ShiftManager` + `ShiftSpreadsheetController` + modal de importação | modelo XLSX, importação e exportação mensal de plantões | referência de setores/categorias, validação por linha com cancelamento total, duplicatas ignoradas e teste do fluxo |
| Duplicação de escala | `ShiftManager` + `DuplicateMonthAction` + modal de cópia | origem, destino e mapeamento por dia ou dia da semana | prévia com conflitos, revisão com exclusão de itens, criação transacional sem sobrescrita e relatório de criados e ignorados |
| Relatórios | `Manager\Reports` + `ReportPdfController` + `WallRosterPdfController` | consulta compartilhada: cobertura, plantões e trocas; indicadores por setor exclusivos da gerência | filtros de mês/setor/categoria, listas paginadas, limpeza, PDF e CSV gerencial com definições e teste do fluxo |
| Scrollbar | regra global de `resources/css/app.css` | rolagem de página e painéis | inspeção no navegador |

## Navegação e permissões

- Links operacionais usam `focus` para plantão, `request` para pedido de troca e `selectedDate` para o calendário. IDs inválidos ou inexistentes retornam 404; autorização permanece no servidor tanto no acesso direto quanto nas atualizações Livewire. Gerentes consultam os registros gerenciais; profissionais preservam o escopo de setor do calendário e dos relatórios e não acessam Escalas ou Trocas da gerência por URL.
- `from` e `context` descrevem a origem com módulos e parâmetros conhecidos. Voltar reconstrói uma rota interna autorizada pelo perfil, sem aceitar URL de retorno arbitrária. Mês, data selecionada, filtros, aba, busca e página são preservados conforme o módulo; até três níveis de origem são mantidos entre módulos. Contexto desconhecido usa um destino permitido de referência. Esses parâmetros não concedem permissão nem executam decisões.
- A seleção de um plantão prevalece sobre mês, status e página da lista, mantendo seus filtros para retorno. Os filtros ficam desativados durante a seleção; “Ver lista de plantões” restaura a lista. Um pedido selecionado pode ser consultado em qualquer situação; os comandos de decisão continuam disponíveis apenas na situação gerencial existente, com Policies e Actions inalteradas. Registro selecionado e indicação textual recebem destaque; abertura e refresh levam o foco ao registro visível. Ao voltar à fila, uma página que deixou de existir após resolução de pendências é ajustada para a última disponível.
- Calendário conserva data, setor e categoria na URL. Trocas mútuas têm acesso à contrapartida; links do calendário apontam para o pedido correspondente. Relatórios da gerência abrem pedidos diretamente, e links para calendário e escala levam o período e os filtros. Na consulta profissional, links e PDFs continuam usando rotas profissionais após atualizações Livewire. PWA mantém navegação autenticada pela rede, sem cache dos registros operacionais.

- Após o login, o usuário é redirecionado ao painel correspondente ao seu perfil.
- O gerente acessa Visão geral, Calendário, Escalas, Trocas, Relatórios, Setores, Categorias e Usuários, nesta ordem. O profissional acessa Minha escala, Calendário e Relatórios.
- Links podem antecipar a navegação com Livewire, mas a autorização permanece obrigatória no servidor.
- A rota ativa recebe indicação visual e `aria-current="page"`.
- No desktop, o toggle alterna a sidebar entre 18rem e 6rem e persiste a preferência no navegador; Usuários é o último item dos módulos gerenciais.
- No celular, o botão “Abrir menu principal” exibe a sidebar como gaveta. A gaveta fecha pelo botão, pelo fundo de proteção, pela tecla `Esc` ou após a escolha de uma rota.
- Uma resposta 403 deve preservar a possibilidade de retorno a uma área autorizada.
- Notificações e Ajuda e versões pertencem ao grupo Suporte e estão disponíveis para gerente e profissional; o número da versão atual aparece na sidebar.
- O item Notificações mostra um contador de não lidas, inclusive quando a sidebar está recolhida, e atualiza o total periodicamente.
- A ação Instalar aplicativo fica oculta até o navegador emitir a oferta nativa e desaparece quando o sistema já está em modo instalado.
- Na autenticação, instalação é uma ação textual secundária e opcional. iPhone e iPad em contexto seguro recebem orientação expansível para usar Compartilhar → Adicionar à Tela de Início no Safari; essa orientação não simula uma oferta nativa. A oferta é consumida uma vez, impede cliques simultâneos e recupera o estado em cancelamento ou falha.

## Fluxos e retorno

A landing oferece um atalho para o conteúdo e âncoras identificadas. Com Lenis, a navegação atualiza o hash e leva o foco à seção após a rolagem; sem JavaScript ou com movimento reduzido, usa a navegação nativa. O menu público é uma expansão não modal: fecha ao escolher uma seção ou usar `Esc`, que devolve o foco ao botão. Sem JavaScript, há links visíveis para as seções. O exemplo de troca apresenta solicitação, aceite e decisão simultaneamente, informa a possibilidade de rejeição e nunca sugere aprovação automática. A prévia não consulta nem publica registros de usuários. O acesso depende de credenciais da instituição; o CTA orienta quem ainda não as possui.

Os formulários de acesso não abrem o teclado automaticamente. E-mail usa teclado apropriado sem correção ou capitalização; senhas mantêm `current-password` ou `new-password`, nome e tipo nativos. Os valores atuais do autofill são sincronizados antes do envio, sem requisição adicional. Após validação, o foco vai ao primeiro campo inválido e a mensagem permanece associada a ele. Falhas de requisição ficam no formulário e permitem tentar novamente; expiração da página oferece recarga deliberada. Duração de sessão, lembrar acesso, limites de tentativas, autorização, tokens de recuperação e regras de senha continuam definidos pelo backend. Confirmações de recuperação e troca de senha permanecem inline, com `role="status"`.

| Operação | Estado em andamento | Sucesso | Falha e recuperação |
| --- | --- | --- | --- |
| Criar ou editar plantão | botão desabilitado e “Salvando…” | mensagem de sucesso e formulário limpo | erros junto aos campos; dados permanecem editáveis |
| Excluir registro | modal identifica o registro e explica vínculos ou efeitos em cascata | item removido e aviso flutuante de sucesso | item permanece disponível e o aviso informa o bloqueio |
| Solicitar ou aceitar troca | modal revisa plantão e participantes; confirmação fica desabilitada durante envio | aviso, comprovante e atualização das listas | validação explica incompatibilidade ou cota e preserva os dados editáveis |
| Aprovar ou rejeitar troca | modal revisa plantão, solicitante, substituto e efeito da decisão | comprovante, aviso e atualização da fila | pedido permanece para nova análise |
| Desfazer troca aprovada | modal revisa plantão, participantes e devolução ao solicitante | comprovante, aviso, plantão devolvido e cota liberada | troca permanece aprovada para nova tentativa |
| Consultar notificações | contador e histórico carregados sem alterar a navegação; leitura em massa exige confirmação | item pode ser marcado como lido e o comprovante permanece disponível | somente notificações do usuário autenticado podem ser consultadas |
| Informar ausência | modal revisa plantão, justificativa e consequência antes do envio | plantão descoberto, alerta gerencial e aviso de sucesso | motivo e arquivo permanecem corrigíveis |
| Criar ou editar cadastro | modal aberto pelo botão “Adicionar” ou pela ação “Editar” | mensagem de sucesso, modal fechado e lista atualizada | modal permanece aberto com erros junto aos campos |
| Conhecer uma nova versão | modal bloqueia a área até uma decisão consciente | versão registrada como visualizada e modal fechado, ou redirecionamento ao manual | sem confirmação, o modal volta a aparecer no próximo acesso |

## Estados de dados

- Listas vazias explicam o que está ausente e onde novos itens aparecerão.
- O calendário está disponível para gerente e profissional, mostra a quantidade de plantões em cada dia e diferencia cobertura completa de plantões sem profissional; dias com 24h cobertas recebem o marcador ✓ com descrição acessível. Filtros de setor e categoria refinam as contagens e a lista do dia, com opção de limpar a seleção. Ao selecionar uma data, lista todos os registros daquele dia. Ações de gestão aparecem somente para gerentes.
- Números do painel representam consultas reais. Zero significa ausência confirmada de registros no filtro descrito.
- O painel gerencial conta plantões descobertos ativos ou futuros (incluindo os iniciados ontem ainda em andamento) e todas as trocas `pending_manager`. Os indicadores globais independem do período da fila e abrem listas com filtros reais; ações por item abrem o plantão ou pedido selecionado, sem aprovar diretamente.
- Hoje, 3 dias e 7 dias são dias de calendário incluindo hoje, de 00:00 até 00:00 após a última data (limite exclusivo). A fila inclui descobertos ainda em andamento e trocas com início anterior ao limite; pedidos com plantão já iniciado continuam visíveis e identificados. Trocas mútuas usam o primeiro início entre os dois plantões. O resumo de 72h conta somente descobertos que iniciam em `[agora, agora + 72h)`, independentemente do filtro. A seleção substitui o período na URL, sem acrescentar uma etapa de histórico por clique; mudar o período volta à primeira página. Recarregar ou voltar de uma lista mantém o filtro escolhido.
- A criação da solicitação notifica o substituto; o aceite notifica os profissionais e todos os gerentes; aprovação ou rejeição notifica solicitante e substituto.
- O comprovante guarda um retrato dos dados no momento do evento e não depende de consultas atuais para exibir nomes, setor, categoria ou horários.
- A fila gerencial pagina oito pendências por vez, sem repetir registros em blocos paralelos. Falta de cobertura tem prioridade sobre trocas; cada grupo ordena início do plantão, criação do pedido e identificador como desempate. As contagens incluem todos os registros elegíveis, independentemente da página. Fila vazia distingue ausência global de pendências de ausência apenas no período escolhido.
- Status nunca depende somente de cor; o badge sempre apresenta um rótulo.
- Em falha de conexão durante uma navegação, o service worker entrega somente a tela offline. Respostas HTML autenticadas e dados operacionais nunca entram no cache.

## Layout responsivo

- O conteúdo usa largura máxima de 76rem e margens adaptáveis.
- Todos os fluxos começam em uma coluna e controles ocupam a largura disponível no celular.
- A sidebar fica fixa e recolhível a partir de `lg`; abaixo desse breakpoint, uma barra móvel compacta apresenta marca e botão do menu, sem exibir o toggle do desktop.
- Modais ocupam a largura disponível no celular, mantêm cabeçalho e ações visíveis e permitem rolagem interna do formulário.
- Tabelas são usadas a partir do breakpoint médio. No celular, os mesmos registros aparecem como cartões com editar e excluir visíveis.
- Métricas do painel se empilham no celular e formam colunas no desktop.
- Cabeçalhos de formulários e confirmações empilham ações no celular e se alinham horizontalmente quando houver espaço.
- Nenhuma tabela ou grupo de ações pode ampliar a página horizontalmente.

## Verificação obrigatória

- `php artisan test`
- `vendor\bin\pint --test`
- `npm run build`
- `npx -p @google/design.md designmd lint DESIGN.md`
- Inspeção no navegador em 320×568, 390×844 e 1440×1000, incluindo console, navegação, formulários, alertas e cartões.


## Análise e decisão no painel gerencial

- Responsáveis canônicos: `ShiftSwapAnalysis`, `AnalyzesShiftSwaps`, parcial `manager/partials/swap-analysis` e `DecideShiftSwapAction`, compartilhados com Trocas.
- Abrir a análise não modifica o pedido. Aprovar ou Rejeitar abre a etapa de confirmação com consequências explícitas. Rejeição exige justificativa de 3 a 1000 caracteres no servidor, inclusive fora do componente.
- São exibidos data, início, término, duração, setor, categoria, participantes e contrapartida. Alertas consideram o cronograma resultante dos dois profissionais, excluindo os plantões que serão transferidos. Encostar é dobra; sobrepor é conflito. Ambos permanecem alertas gerenciais conforme a regra vigente; não foi criada proibição institucional adicional.
- Cota: quatro participações aprovadas, pelo mês de criação da solicitação. Limite atingido impede aprovação, mas permite análise e rejeição.
- A confirmação usa estado bloqueado do Livewire e uma assinatura da análise. A Action relê e bloqueia participantes, gerente, plantões em ordem estável e pedido, revalida permissão atual, atribuição da contrapartida, status e cota na transação. Mudanças desde a análise exigem fechá-la e abri-la novamente.
- Clique repetido ou duas decisões concorrentes não criam duas decisões/comprovantes. Deadlocks recebem até três tentativas; conflito persistente retorna mensagem para consultar novamente, sem sucesso antecipado. Falhas de rede mantêm a análise e orientam conferir o estado antes de repetir.
- Após sucesso, fila e indicadores são recalculados, filtros mantidos, comprovante exibido e contador de notificações atualizado. Notificações históricas permanecem imutáveis na mesma transação da decisão.
- Modal: foco inicial em Fechar, Tab restrito ao diálogo, fundo inerte, Escape e cancelamento bloqueados durante a confirmação, foco de retorno e campos com erros associados. Movimento reduzido preservado.
- Verificação: PHPUnit/Livewire, processos PHP concorrentes com SQLite descartável, navegador 320/390/1440 px, teclado, axe AA, clique repetido, análise desatualizada e PWA. O teste SQLite não certifica o mecanismo de locks do MySQL de produção.


## Realocação assistida de plantões descobertos

- Responsáveis: `ShiftReallocation` (disponibilidade), `ReallocateShiftRequest`, `ReallocateShiftAction`, `ReallocatesShifts` e parcial `manager/partials/shift-reallocation`; modal e feedback compartilhados com análise de trocas.
- “Preencher plantão” abre o registro específico no dashboard, calendário ou Escalas. A navegação contextual anterior permanece disponível e preserva filtros. Só a gerência pode preencher plantões descobertos ativos ou futuros; encerrados e já agendados não são sobrescritos.
- Elegibilidade cadastral usa perfil Profissional, setor e categoria idênticos aos do plantão. O cadastro não tem campo ativo/inativo; não foi inventado um novo estado ou limite institucional. Cadastros excluídos ou que perderam esses vínculos deixam de ser elegíveis.
- Disponibilidade considera plantões agendados e intervalos reais `[início, término)`, reutilizando `ShiftPeriod`. Inclui noites, mês seguinte e término explícito dos plantões de 24h. Sobreposição impede seleção neste fluxo assistido, assim como na cópia de escalas existente; ele não muda o formulário geral nem a regra de aprovação de trocas. Plantões que apenas encostam permanecem elegíveis, com alerta de dobra para avaliação do gerente. A cota de trocas não vira limite de realocação.
- Só profissionais elegíveis e sem conflito são listados. Uma contagem informa quantos profissionais compatíveis foram excluídos por sobreposição. Estado vazio explica a necessidade de conferir cadastros e escala.
- Seleção e prévia não alteram dados. A prévia informa o profissional anterior, o escolhido, Descoberto → Agendado e horários preservados. Confirmação é explícita e a escolha revisada fica bloqueada no Livewire.
- Antes de gravar, a Action relê e bloqueia gerente/profissional em ordem estável, plantão e cronograma relevante; revalida Policy, cadastro, horários, estado e assinatura da prévia. Alterações de disponibilidade ou novos alertas exigem revisão; concorrência persistente recebe mensagem para consultar novamente após até três tentativas transacionais.
- O formulário geral de edição mantém seus critérios e passa por `UpdateShiftAction`, com assinatura do registro aberto e transação. Uma edição antiga não pode sobrescrever uma realocação confirmada; precisa ser aberta novamente.
- Auditoria em `shift_reallocations` registra gerente, data, profissional e snapshots completos de antes/depois na mesma transação da atribuição. Falha de auditoria desfaz a atribuição. Chaves estrangeiras tornam-se nulas na exclusão de cadastros; IDs e nomes nos snapshots preservam a referência histórica. O registro selecionado em Escalas exibe até dez realocações recentes.
- Após confirmação, indicadores/fila ou calendário são recalculados no próprio componente, com filtros preservados. Ausências, pedidos de troca, comprovantes e notificações anteriores não são reescritos; esta realocação não aprova/rejeita trocas nem envia notificações adicionais.
- Teclado e celular: foco inicial em Fechar, Tab restrito ao modal, corpo rolável com foco, ações sempre acessíveis, retorno ao acionador/título e cancelamento bloqueado durante confirmação. Somente um painel de análise fica aberto no dashboard.
- Verificação: regressões Livewire, duas realocações em processos separados com SQLite descartável (mesmo plantão e plantões sobrepostos para o mesmo profissional), noites, 24h, virada do mês, permissão revogada, stale preview, rollback e histórico. Navegador em 320/390/1440 px, viewport reduzido, axe AA, PWA e refresh. SQLite não certifica os locks do MySQL de produção.


## Pesquisa, filtros e paginação (1.28.0)

- Responsáveis: `OperationalFiltersRequest`, `OperationalFilters`, `FiltersOperationalLists`, componente `operational-filters` e `ShiftReportService::forScreen`. Os filtros da lista de Escalas são independentes dos campos de cadastro/edição.
- Filtros combinam nome do profissional (solicitante ou substituto em Trocas), setor, categoria, situação e datas. Pesquisa textual de 350ms, parâmetros vinculados e `%`/`_` literais. Datas se referem ao início do plantão, com os dois dias incluídos; em trocas, setor/categoria/período pertencem ao plantão solicitado, não à contrapartida.
- Em Escalas e Minha escala, datas explícitas substituem o mês; sem datas, vale o mês exibido. Descobertos ativos/futuros conserva abrangência entre meses e inclui noturnos em andamento. Há opção separada de descobertos no mês/período. Trocas gerenciais inicia em Aguardando gerência; limpar restaura essa situação. As dez aprovações futuras recentes permanecem disponíveis nessa consulta.
- Minha escala pesquisa nomes nas trocas, mantendo apenas plantões próprios e pedidos em que o usuário participa; setor cadastrado é imposto no servidor. Situação dos plantões e situação do histórico de trocas são seleções distintas. Pedidos aguardando resposta continuam separados do histórico.
- URL preserva pesquisa, seleção, período e paginação para refresh e compartilhamento. Alterar filtros reinicia as páginas; limpar mantém mês e origem contextual. Retorno de registros por ID preserva filtros da lista; seleção exata ignora esses filtros sem conceder autorização. Contextos têm no máximo 16 campos e três níveis; parâmetros estruturados/malformados são recusados antes da hidratação. Valores inexistentes, datas invertidas e situações inválidas mostram erro e não ampliam a consulta.
- Relatórios mantêm mês, setor e categoria globais. Nome/equipe filtra só profissionais; situação filtra só trocas criadas no mês. Indicadores conservam o mesmo escopo dos PDFs. Profissionais, trocas e ausências têm páginas independentes de dez registros; nomes e status são filtrados em SQL, com totais em SQL e relações carregadas em lote. PDF e grade mensal de afixação continuam completos; busca/status das listas não alteram exportações.
- Setor profissional e visibilidade das próprias trocas/ausências são impostos nas consultas de relatório, inclusive diante de parâmetros forjados. Não há alteração de aprovação, cota, cadastro ou dados históricos.
- Intervalos usam limites diretos sobre `date` e `created_at`, aproveitando os índices existentes; Uma migração reversível acrescenta índice de `shift_swaps.created_at`: a consulta mensal sem filtro de situação precisava varrer o índice composto por status e ordenar temporariamente, conforme EXPLAIN no SQLite. O índice permite busca por intervalo e ordenação cronológica. Não reescreve registros; planos de produção devem ser conferidos no MySQL. Pesquisa por trecho de nome não seria atendida por índice B-tree comum.


## Auditoria operacional (1.29.0)

- Responsáveis: `HasOperationalAudit`, `OperationalAudit`, `OperationalAuditContext`, builders de escrita e `OperationalAuditEventPolicy`; visualização em `AuditHistory` com `AuditHistoryRequest`. Captura é feita no backend, inclusive em Actions, imports, quiet writes e operações de demonstração.
- Antes/depois vem do registro relido sob lock e do estado persistido. Modelo e auditoria são gravados juntos; falha ou rollback remove ambos. Actions preservam retries e autor explícito; UUID agrupa os eventos da operação. Atualizações em lote que ignorariam a captura são recusadas e os caminhos existentes foram adaptados.
- Histórico é append-only no aplicativo, com triggers que bloqueiam UPDATE/DELETE no banco. Todos os perfis são impedidos de editá-lo; a Policy de consulta confere o gerente atual. Não há cascata de autoria nem reescrita quando o autor/registro é excluído. A migração recusa rollback com eventos existentes.
- Links por plantão, troca, usuário, setor e categoria abrem histórico por tipo/ID, mantendo filtros de origem no retorno. Filtros combinam ação, autor, tipo/ID e dias completos, com debounce de 350ms e paginação de dez eventos. URLs/entradas inválidas são recusadas ou mostram erro sem ampliar a consulta.
- Há apenas diferenças relevantes; senhas/tokens, texto de justificativas/motivos, anexos e comprovantes não são copiados. E-mail alterado recebe somente uma indicação. Metadados de sessão/versão vista e no-ops não geram evento.
- A captura começa na instalação; não há reconstrução artificial do passado. Retenção, credenciais administrativas, limites das triggers e plano institucional estão em `docs/operational-audit.md`. Nenhum prazo ou expurgo automático foi aplicado.

## Alertas preventivos de plantões

- SaveShiftRequest e ShiftReallocation reutilizam ShiftAssignmentAnalysis, ShiftPeriod e ShiftCoverage. O próprio ID é excluído da agenda na edição; horários reais incluem término no dia seguinte, virada de mês e 24h.
- Incompatibilidade bloqueia cadastro/realocação. Sobreposição permanece aviso na criação/edição e bloqueio na realocação; consecutividade permanece aviso de dobra. Nenhum limite de descanso ou horas foi criado. Critérios pendentes e classificação estão em docs/preventive-shift-alerts.md.
- Salvar com avisos abre revisão sem persistir. Revisar dados conserva valores; Confirmar com os avisos revalida tudo sob locks no backend. Mudanças posteriores pedem nova revisão; a assinatura da edição impede sobrescrever um registro desatualizado.
- Modais compartilham foco, fundo inerte, carregamento e tratamento de erro de transporte. Durante a requisição, confirmação/cancelamento são desabilitados; a prévia não comunica sucesso antes da persistência. Notificações, comprovantes e histórico transacional permanecem existentes.

## Convites e ativação

- Gerência cria convites individuais para novos profissionais. Formulário reutiliza cadastro e fixa o perfil, sem solicitar senha. Cadastro existente não é sobrescrito; registro público continua indisponível.
- Status Pendente/Aceito/Expirado/Revogado é equivalente em tabela e cartão. Reenvio invalida link anterior; revogação exige confirmação e preserva cadastro. Ambos revalidam Policy/estado no backend; conta aceita segue recuperação de senha.
- Página pública reutiliza auth/form e layout guest, sem e-mail/ID/nome. Token é retirado da URL; referência bloqueada identifica uma autorização somente na sessão atual, separada por aba. Refresh funciona, compartilhamento dessa referência não concede acesso em outra sessão.
- Profissional define senha e confirmação, consumidas com o convite em transação. Pendentes não entram nem usam recuperação para ativação. Sucesso redireciona ao login; erros de convite e mensagens de recuperação não revelam existência/situação da conta.
- Envio é solicitado após commit, com fila criptografada e verificação da emissão no worker. UI distingue solicitação de envio de entrega; links não são mostrados na gerência nem enviados ao log. Configuração e limites estão em docs/account-invitations.md.

## Indicadores operacionais por setor

- Gerentes consultam situação atual e registros do período separadamente. Cobertura é proporção de plantões agendados com profissional atribuído entre todos os iniciados no mês; não equivale a horas cobertas. Sem cobertura e status Descoberto são contadores distintos.
- Trocas usam o mês de criação do pedido e situação atual opcional; ausências usam o mês de início do plantão e permanecem após realocação. Setor/categoria atuais não permitem reconstruir um retrato histórico. Definições completas: `docs/sector-report-indicators.md`.
- Mês/setor/categoria filtram os indicadores; busca por profissional limita apenas sua lista. Setores sem movimento mostram zero, proporções sem denominador mostram “—”. A tela pagina dez setores, conserva `sectorsPage` no retorno contextual e reinicia a página após alterar/limpar filtros.
- PDF inclui os novos indicadores, a busca da lista de profissionais e situação da lista de trocas. CSV por setor exporta todas as páginas do agregado e registra filtros/definições/geração. Filtros de exportação inválidos são rejeitados. CSV e novos indicadores são exclusivos da gerência; consultas profissionais preservam setor fixo e visibilidade das listas anteriores.
- Tabelas usam cabeçalhos semânticos e rolagem acessível por teclado; cartões equivalentes no celular. Sem redesenho, gráficos desnecessários ou avaliação profissional.

As definições completas dos indicadores ficam em um disclosure nativo com summary, acionável por teclado. O período e a fórmula resumida permanecem visíveis; no PDF, as definições são impressas integralmente.

## Razões de bloqueio na análise de trocas

O modal existente mostra conferência dos dois plantões, cotas atuais e participação após aprovação. Avisos de horário usam borda primary/texto ink e ficam separados dos impedimentos em danger. Um botão Aprovar desabilitado referencia as razões específicas com aria-describedby; não há mensagem genérica de atribuição ou cota. Falha de revisão mostra a condição atual e oferece Atualizar análise, que apenas recarrega a conferência. O erro recebe foco e a atualização retorna ao início do corpo do modal. Decisões concluídas ficam somente para consulta, sem ações habilitadas. Composição, tipografia, tokens e confirmação explícita são preservados.

Impedimentos ficam no início da análise; ausência de conflito não os oculta. Atribuição prevista e atual identificam os participantes por nome/ID quando conhecidos.

## Continuidade da navegação e dos modais

Livewire/Alpine e os interceptores iniciam uma vez por documento. Se o JavaScript instalado mudar durante wire:navigate, o destino é recarregado antes de iniciar outro runtime, mantendo a URL e seus filtros. Os modais conservam foco, fundo inerte, validação e cancelamento; os controles ficam ocupados apenas durante a requisição. Detalhes e diagnóstico estão em docs/frontend-runtime.md.

## Consulta completa de trocas

Aguardando decisão mantém a fila operacional; Histórico de trocas inclui todas as situações e datas, com dez resultados por página SQL e contagem total. Pesquisa geral e campos separados por participante combinam com período inclusivo do início do plantão solicitado, setor, categoria e situação. Ordenação por solicitação usa ID como desempate. Trocas mútuas mostram os dois intervalos e os profissionais previstos/atuais com rótulos diferentes. Filtros reiniciam a página; limpar mantém a visão atual. Estado, detalhes e retorno persistem por URL validada. Comprovante atual não substitui eventos anteriores. Desfazer depende da Policy e explica impedimentos sem mudar as regras de reversão. Contrato detalhado em docs/manager-swap-history.md.

## Reversão administrativa de trocas

Desfazer está disponível para revisar qualquer pedido aprovado no histórico completo. Abrir ou atualizar revisão não escreve dados. A confirmação exige justificativa e prévia emitida pelo servidor; IDs e assinatura são Locked. Os dois plantões de uma troca mútua são revisados e restaurados juntos ou nenhum é modificado. Após sucesso, modal fecha, fila e comprovante atualizam e foco retorna ao controle de origem ou ao título da lista. Motivos específicos e erros recebem foco; erro de rede mantém revisão para conferência. Plantões iniciados, passados, concluídos, alterados ou sem evidência original oferecem análise administrativa separada de somente leitura, com links para o histórico. Não existe confirmação forçada.
