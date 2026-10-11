# Navegação móvel por perfil

A sidebar desktop permanece com os mesmos destinos, ordem, destaque e preferência de recolhimento. Abaixo de lg, a barra inferior contém quatro destinos:

| Perfil | Início | Segundo destino | Trocas | Menu |
| --- | --- | --- | --- | --- |
| Gerente | manager.dashboard | manager.calendar | manager.swaps | demais módulos gerenciais e suporte |
| Profissional | professional.dashboard | Escala: #schedule-title | #my-swaps-title | calendário, relatórios e suporte |

O profissional já possui seus plantões, recebimentos e histórico de trocas em MySchedule. Não foi criada uma rota nova para esses módulos. Na própria página, os três atalhos são âncoras nativas e preservam mês, filtros, paginação e formulário local. A partir de outro módulo, usam a rota profissional existente e o fragmento de destino. Trocas oferece acesso aos pedidos aguardando resposta quando presentes nos filtros. As âncoras recebem foco e margem para o cabeçalho móvel. Atualizar ou compartilhar a URL conserva o destino; consultas e ações continuam verificando sessão e autorização no servidor (uma âncora local não faz nova consulta).

## Menu e autorização

O Menu não repete os três destinos principais. Gerentes acessam Escalas, Relatórios, Histórico operacional, Setores, Categorias, Usuários e Relatos; Policies de consulta são verificadas quando aplicáveis, além do papel e das rotas existentes. Profissionais acessam Calendário e Relatórios. Ambos encontram Notificações, Ajuda e versões, instalação quando suportada e Sair. A saída permanece POST com CSRF, invalidação da sessão e redirecionamento ao login. Não há aprovação, troca de perfil ou permissão concedida pela navegação.

MobileNavigation guarda somente o nome da rota como propriedade Locked para conservar o destaque durante polling e atualizações Livewire. O perfil é obtido do usuário autenticado a cada renderização. Prefixos de rotas mantêm o destaque em subrotas; relatórios e demais módulos ficam sob Menu. Alterar links ou IDs no navegador não muda as Policies, middleware ou escopo de setor dos profissionais.

## Contadores

Trocas mostra pedidos aguardando decisão para gerentes (PendingManager) e pedidos dirigidos ao próprio substituto profissional aguardando resposta (PendingProfessional). Não usa um mês artificial nem interpreta pendência como aprovação disponível. Menu mostra notificações não lidas da própria conta. São agregações SQL, sem carregar pedidos/notificações em memória. O evento notifications-updated atualiza os números; polling de 30 segundos na barra visível cobre mudanças externas, sem polling da barra oculta no desktop. A consulta não marca leitura nem grava histórico. A sidebar mantém seu contador existente.

## Comportamento e geometria

A barra usa ícones do sistema, rótulos curtos, quatro colunas e alvos de pelo menos 44px. A rota ativa recebe aria-current, cor primary-soft e texto primary-strong; os badges possuem descrição textual. A área inferior do documento reserva a altura da barra mais safe-area-inset-bottom, protegendo conteúdo, paginação e rodapé. Modais/sheets ficam acima da barra; toasts permanecem no topo. Ao detectar viewport reduzida durante edição de campo, a barra fica oculta e sua reserva é removida. Ela retorna após o teclado/edição; listeners de viewport e foco são removidos ao navegar. O teste simula redução de viewport, sem certificar o teclado físico de cada aparelho.

Menu reutiliza action-modal com estado local Alpine (client-state e close-event): começa fechado, mantém foco preso e fundo sem scroll enquanto aberto, fecha por Escape/X/fundo/ação e restaura o acionador. Links fecham o Menu antes da navegação. Mudar para desktop também fecha e libera o fundo. Atualizar contadores conserva o painel e o destaque da rota. PWA continua usando a rede para páginas autenticadas, sem novos caches operacionais. Movimento reduzido usa a regra global existente.

## Verificação

MobileNavigationTest cobre destinos, menus sem duplicação, isolamento de perfil, Policies/rotas protegidas, contagens, leitura intacta, propriedade Locked, logout e sessão ausente. Testes JavaScript cobrem âncoras, teclado, listeners e diálogo local. npm run test:mobile-navigation:browser cobre ambos os perfis em 320/390/1440px, sidebar desktop, links/refresh, foco, trap, axe, reserva do rodapé, teclado simulado, updates Livewire, Menu ao redimensionar, logout, sessão expirada e PWA. Usar somente banco descartável com MOBILE_NAV_MANAGER, MOBILE_NAV_PROFESSIONAL, MOBILE_NAV_PASSWORD; opções MOBILE_NAV_URL, MOBILE_NAV_SCREENSHOTS, PLAYWRIGHT_MODULE, PLAYWRIGHT_CHANNEL e AXE_MODULE.
