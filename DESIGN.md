---
version: alpha
name: "Escala Fácil"
description: "Sistema operacional de plantões com linguagem clínica, clara e confiável."
colors:
  ink: "#12333A"
  muted: "#5E7377"
  primary: "#0F6674"
  primary-strong: "#0A4D59"
  primary-soft: "#DFF1F1"
  coverage: "#11856B"
  canvas: "#F3F8F7"
  surface: "#FFFFFF"
  line: "#C9DAD7"
  danger: "#B42318"
  danger-soft: "#FEF3F2"
  focus: "#0E7490"
typography:
  sans:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
  mono:
    fontFamily: "IBM Plex Mono, ui-monospace, monospace"
rounded:
  DEFAULT: "0.5rem"
  sm: "0.375rem"
  md: "0.5rem"
  lg: "0.75rem"
  full: "9999px"
spacing:
  field-gap: "1.25rem"
  section-gap: "2.5rem"
  page-max: "76rem"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.surface}"
    typography: "{typography.sans}"
    rounded: "{rounded.md}"
    padding: "0.75rem"
  button-primary-hover:
    backgroundColor: "{colors.primary-strong}"
  focus-indicator:
    backgroundColor: "{colors.focus}"
  card:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "1.5rem"
  card-secondary-text:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.muted}"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "3rem"
  divider:
    backgroundColor: "{colors.line}"
    height: "1px"
  status-success:
    backgroundColor: "{colors.coverage}"
    textColor: "{colors.surface}"
  status-pending:
    backgroundColor: "{colors.primary-soft}"
    textColor: "{colors.primary}"
  status-error:
    backgroundColor: "{colors.danger-soft}"
    textColor: "{colors.danger}"
  navigation:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
  data-table:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  shift-card:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
    padding: "1.5rem"
  status-badge:
    backgroundColor: "{colors.primary-soft}"
    textColor: "{colors.primary}"
    rounded: "{rounded.full}"
  file-input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.muted}"
    rounded: "{rounded.md}"
  coverage-board:
    backgroundColor: "{colors.primary-strong}"
    textColor: "{colors.surface}"
    rounded: "{rounded.lg}"
  landing-page:
    backgroundColor: "{colors.canvas}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  operational-alert:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  confirmation-modal:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.lg}"
  toast-success:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.coverage}"
    rounded: "{rounded.lg}"
  toast-error:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.danger}"
    rounded: "{rounded.lg}"
---

# Escala Fácil Design System

## Overview

### Creative North Star

Um quadro de plantões hospitalar bem organizado: fundo claro, linhas precisas, hierarquia direta e faixas de cor usadas somente para indicar estado e cobertura.

### Product context and register

- **Audience and primary job:** gestores organizam escalas e decidem trocas; profissionais consultam plantões e respondem a solicitações.
- **Target market(s) and evidence:** instituições brasileiras, conforme requisitos, domínio e interface em português do Brasil.
- **Locale(s) and language policy:** `pt-BR` é o idioma principal; mensagens internas e validações devem usar linguagem simples em português.
- **Usage scene:** uso recorrente em desktop e celular, inclusive durante rotinas rápidas de plantão.
- **Register:** produto operacional; telas priorizam clareza, estado e rastreabilidade.
- **Memorable signature:** pequenas faixas horizontais inspiradas nos blocos de uma escala distinguem estados sem dominar o conteúdo.
- **Restraint:** formulários, permissões e decisões de troca usam padrões familiares e pouca decoração.
- **Anti-references:** painéis genéricos com excesso de gradientes, vidro, cartões flutuantes ou cores sem significado.
- **Token ownership/runtime mapping:** este documento espelha os tokens canônicos implementados em `resources/css/app.css`; mudanças devem ser feitas nos dois arquivos no mesmo commit.

## Colors

`primary` e `primary-strong` conduzem navegação e ações principais. `coverage` representa confirmação ou cobertura. `canvas`, `surface` e `line` criam camadas estáveis. `danger` aparece apenas em erros ou ações destrutivas. O foco usa `focus` com contorno externo visível. O MVP oferece tema claro.

## Typography

Instrument Sans atende títulos, texto e controles. IBM Plex Mono aparece em rótulos curtos, índices e dados operacionais. Títulos usam peso 600 e tracking levemente fechado; corpo mantém line-height amplo. Controles não usam caixa alta. Rótulos operacionais em mono podem usar caixa alta com espaçamento.

## Layout

O celular é a base canônica de composição para todos os perfis. O conteúdo começa em uma coluna, com margens de 1rem, controles na largura disponível e cartões para registros; os breakpoints `sm`, `md`, `lg` e `xl` apenas ampliam espaço ou acrescentam colunas. O conteúdo autenticado tem largura máxima de 76rem e passa a dividir a tela com uma sidebar de 18rem em `lg`, recolhível para 6rem com preferência persistida localmente. A autenticação ganha uma coluna contextual somente em telas grandes. Elementos interativos mantêm altura mínima de 40px, preferencialmente 48px em formulários, e nenhum conteúdo pode ampliar a página horizontalmente.

## Elevation & Depth

Hierarquia vem primeiro de superfícies e bordas. A sombra `soft` é reservada a cartões principais. Formulários e cabeçalhos não dependem de blur. Overlays futuros devem usar fundo escurecido e preservar foco.

## Shapes

Campos e botões usam raio de 0,5rem; cartões usam 0,75rem. Pílulas ficam restritas a status curtos. Ícones de marca podem usar cantos mais suaves. Linhas têm 1px e cor `line`.

## Components

### Foundational visual states

Hover altera tom sem mover o componente. Foco mostra contorno `focus`. Disabled reduz opacidade e bloqueia interação. Busy preserva dimensões e troca o rótulo por verbo no gerúndio. Sucesso e erro combinam cor, borda e texto; nenhum estado depende somente de cor.

### Buttons and actions

A ação primária usa fundo `primary`; ações secundárias usam superfície e borda. Rótulos começam por verbos. Ações destrutivas ficam separadas e usam confirmação em modal com a consequência descrita antes da decisão.

### Navigation and data display

A navegação autenticada usa uma sidebar escura com marca, identidade do usuário, ícones acompanhados por texto e saída no rodapé. No desktop ela permanece fixa e pode ser recolhida por um toggle; no estado compacto, títulos acessíveis preservam o significado dos ícones. No celular abre como gaveta por um botão identificado, usa fundo de proteção e fecha por botão, clique externo, tecla `Esc` ou seleção de destino. Usuários permanece como o último módulo gerencial; Ajuda e versões aparece em um grupo separado de suporte para os dois perfis, junto da versão atual. Tabelas futuras devem ter cabeçalhos persistentes quando densas e rolagem horizontal dentro do painel em telas estreitas. Badges exibem texto além da cor.

O calendário compartilhado por gerentes e profissionais apresenta sete colunas mesmo no celular, com números, contagens e marcadores compactos. A lista textual abaixo do mês concentra nome do profissional, horário, setor, categoria e status do plantão selecionado; atalhos de gestão aparecem somente para quem possui a permissão correspondente.

Os rótulos Menu, Suporte e versão da sidebar usam branco com 65% de opacidade para manter contraste sobre `primary-strong`. A paginação da fila usa Anterior e Próxima como botões nativos, com limites desativados e página atual textual; a navegação retorna ao título da fila, respeitando a barra móvel.

O painel gerencial usa o quadro `coverage-board` como ponto focal único: a faixa escura reúne métricas operacionais reais e acionáveis, com trilhos verdes, vermelhos e âmbar para cobertura, descoberta e decisão. Abaixo, uma fila única de linhas `attention-row` apresenta prioridade textual, tipo, data e duração, setor, participantes, antiguidade e ação contextual. Falta de cobertura vem antes de trocas; dentro de cada grupo, início do plantão e antiguidade ordenam os registros. Os filtros Hoje, 3 dias e 7 dias usam botões com estado pressionado, mantendo a seleção na URL. As 72 horas corridas têm um resumo separado e limites explícitos. No celular, cada linha se empilha, preservando todos os dados e a ação. Atalhos se limitam a dois links compactos da escala; cadastros permanecem na sidebar.

### Forms and overlays

A autenticação mantém duas colunas institucionais a partir de `lg`, sem círculos ou faixas decorativas. A coluna contextual explica plantões, trocas e cobertura; a coluna de acesso usa altura natural, `100svh` como mínimo, margens de área segura e rolagem do documento. No celular, marca, título e formulário começam no topo, sem autofoco que abra o teclado. O formulário fica ancorado mesmo quando a oferta de instalação aparece. A ação Entrar é primária; instalação é opcional e usa uma ação textual após a orientação de acesso. Os campos de senha compartilham o componente `auth/password-field`, sempre começam mascarados e têm controle com nome acessível e estado pressionado. Placeholders e foco usam os tokens de contraste do produto. Confirmações de recuperação permanecem junto ao formulário, na variante `auth-session-status`, para não depender de um aviso temporário.

Rótulos ficam sempre visíveis. Erros aparecem junto ao campo com `aria-invalid` e `aria-describedby`. Formulários usam `novalidate` para uma experiência consistente. Senhas têm controle de exibição e autocomplete adequado. Alertas usam `role="status"` ou tratamento semântico equivalente. Nos módulos com CRUD, o botão “Adicionar” abre o formulário em um modal; edição reutiliza o mesmo componente, com título e ação contextualizados. No celular, o modal ocupa a altura disponível e mantém o corpo rolável entre cabeçalho e rodapé fixos.

O modal de nova versão é deliberado e não fecha por clique externo: o usuário escolhe continuar ou abrir o manual. O cabeçalho escuro o distingue de formulários, e os destaques usam uma lista curta com texto. A Central de Ajuda começa por um roteiro numerado de implantação, seguido por manuais de cada perfil, fluxos críticos, referência de status, solução de problemas e linha do tempo de versões. Atalhos, cartões e acordeões permitem leitura progressiva no celular e consulta detalhada no desktop.

O modal de confirmação é reservado a ações com efeito relevante: excluir registros, enviar ou aceitar trocas, decidir uma troca, registrar ausência e alterar a leitura de todas as notificações. Ele usa `role="alertdialog"`, apresenta o registro e a consequência, inicia o foco no cancelamento e mantém verbos específicos nos botões. O fundo não executa nem confirma a ação; `Esc` equivale a cancelar.

Mensagens após mutações aparecem em avisos flutuantes no topo. Sucesso fecha automaticamente após tempo suficiente para leitura e pode ser dispensado antes; erro permanece até o fechamento manual. Ícone, título e texto acompanham a cor para que o significado não dependa apenas dela. No celular, o aviso respeita as margens da tela e não altera a geometria do conteúdo.

A ação **Instalar aplicativo** aparece somente quando o navegador disponibiliza o fluxo nativo de instalação e usa o mesmo padrão dos demais itens de suporte. A tela offline preserva a marca e informa claramente que dados operacionais exigem conexão, sem simular conteúdo desatualizado.

A Central de Notificações usa cartões com uma faixa suave para itens não lidos. Cada cartão contém mensagem, ações de leitura e o comprovante em uma grade compacta; no celular, os campos do comprovante formam uma coluna legível. A referência monoespaçada e o badge de situação reforçam a rastreabilidade sem criar uma estética separada do restante do sistema.

### Iconography

Ícones futuros devem usar traço simples de 1,5 a 2px, entre 16 e 20px, acompanhados por texto quando representam uma ação. O símbolo da marca é o único ícone preenchido recorrente.

### Landing page

A página pública usa os mesmos tokens do sistema (`ink`, `muted`, `primary`, `primary-strong`, `primary-soft`, `coverage`, `canvas`, `line`) e a mesma tipografia (Instrument Sans para títulos e texto, IBM Plex Mono para rótulos operacionais). Gradientes, vidro (`backdrop-blur`), blobs com blur, sombras customizadas e deslocamentos no hover foram removidos; botões usam raio de 0,5rem e pílulas ficam restritas a status curtos. O bloco de conversão segue o padrão do quadro de cobertura: faixa escura em `primary-strong` com texto claro. A navegação pública contém apenas âncoras da própria página e o acesso à plataforma; o menu móvel usa rótulo dinâmico, controle de expansão e fechamento por `Esc`.

A apresentação inicial divide texto e prévia em duas colunas a partir de `lg`; no celular, a prévia segue as ações. O cartão de plantão e os badges reutilizam os componentes do aplicativo, com dados ilustrativos sem consulta ao banco. O chassi de celular, as conversas fictícias e os selos de confiança decorativos dão lugar à informação operacional. A largura acompanha o limite de 76rem do produto; seções usam 3,5rem, 4rem e 5rem de respiro vertical conforme o breakpoint, sem altura mínima artificial. Títulos de seção são alinhados à esquerda, benefícios formam listas e exemplos aparecem após uma divisória. Comparação, recursos e sequência de troca usam bordas e superfícies, sem sombras adicionais. O CTA distingue quem já tem credenciais de quem precisa consultar a gerência. Textos descrevem capacidades verificadas, sem prometer tempo de aprovação, segurança absoluta ou resultados sem evidência.

### Motion

Transições de cor duram até 160ms e comunicam resposta. Carregamentos mantêm geometria. `prefers-reduced-motion` reduz animações e transições a duração mínima. Na landing, Lenis e animações GSAP são ignorados com movimento reduzido. Todas as etapas do exemplo de troca permanecem visíveis; somente a linha de sequência acompanha a rolagem e fica completa com movimento reduzido. Animações nunca controlam a visibilidade do conteúdo.

### Content and data visualization

A voz é direta e respeitosa. Estados dizem o que ocorreu e o próximo passo. Datas e horários futuros seguem `pt-BR`. Gráficos futuros precisam de legenda textual e alternativa tabular.

## Do's and Don'ts

- **Do:** usar as faixas de escala para reforçar estado ou sequência.
- **Do:** manter ações, permissões e mensagens próximas do contexto que as produz.
- **Don't:** transformar cada bloco em cartão ou pílula decorativa.
- **Don't:** esconder rótulos, erros ou ações essenciais atrás de ícones sem texto.
