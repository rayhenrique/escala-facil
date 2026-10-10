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
| Relatórios | `Manager\Reports` + `ReportPdfController` + `WallRosterPdfController` | consulta compartilhada entre gerente e profissional: resumo de cobertura, distribuições, desempenho e trocas | filtros de mês, setor e categoria, limpeza, exportação em PDF e teste do fluxo |
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
