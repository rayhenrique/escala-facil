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
| Seleção | elemento HTML `select` com `field-input` | simples | rótulo, teclado e valor persistido |
| Data e período | inputs HTML `date`, `time` e `month` | data, hora e mês | locale visível e validação no servidor |
| Badge de status | `resources/views/components/status-badge.blade.php` | enums de plantão, troca e ausência | texto semântico e contraste |
| Cartão de plantão | `resources/views/components/shift-card.blade.php` | profissional, gerência e alerta | conteúdo equivalente em desktop e celular |
| Cartão de cadastro | classe `record-card` em `resources/css/app.css` | usuários, setores e categorias | conteúdo e ações equivalentes à tabela |
| Modal de cadastro | classes `modal-*` em `resources/css/app.css` | criar e editar plantões, usuários, setores e categorias | abertura por botão, foco inicial, validação, cancelamento e rolagem móvel |
| Modal de confirmação | `resources/views/components/confirmation-modal.blade.php` | exclusões, trocas, ausência e leitura em massa | registro e consequência visíveis, foco no cancelamento, Escape, autorização e bloqueio durante o processamento |
| Calendário mensal | `MonthlyCalendar` + classes `calendar-*` | consulta compartilhada, filtros de setor/categoria e ações gerenciais condicionais | navegação entre meses, botão Hoje, seleção de dia, filtros com limpeza, lista completa de plantões, ações contextuais por plantão só para gerentes, indicador de carregamento e ausência de ações sem permissão |
| Painel gerencial | `Manager\Dashboard` | cobertura das próximas 72h, plantões descobertos e trocas pendentes | foco operacional com próximo descoberto e pendência mais antiga, atalhos contextuais, listas completas preservadas e contagens reais |
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

## Fluxos e retorno

A landing oferece um atalho para o conteúdo e âncoras identificadas. Com Lenis, a navegação atualiza o hash e leva o foco à seção após a rolagem; sem JavaScript ou com movimento reduzido, usa a navegação nativa. O menu público é uma expansão não modal: fecha ao escolher uma seção ou usar `Esc`, que devolve o foco ao botão. Sem JavaScript, há links visíveis para as seções. O exemplo de troca apresenta solicitação, aceite e decisão simultaneamente, informa a possibilidade de rejeição e nunca sugere aprovação automática. A prévia não consulta nem publica registros de usuários. O acesso depende de credenciais da instituição; o CTA orienta quem ainda não as possui.

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
- O painel gerencial mostra plantões descobertos de hoje em diante e trocas com status `pending_manager`.
- A criação da solicitação notifica o substituto; o aceite notifica os profissionais e todos os gerentes; aprovação ou rejeição notifica solicitante e substituto.
- O comprovante guarda um retrato dos dados no momento do evento e não depende de consultas atuais para exibir nomes, setor, categoria ou horários.
- Alertas exibem no máximo quatro itens por grupo e oferecem acesso à lista completa quando houver mais registros.
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
