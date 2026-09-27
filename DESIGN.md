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
  operational-alert:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
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

A ação primária usa fundo `primary`; ações secundárias usam superfície e borda. Rótulos começam por verbos. Ações destrutivas futuras ficam separadas e usam confirmação contextual.

### Navigation and data display

A navegação autenticada usa uma sidebar escura com marca, identidade do usuário, ícones acompanhados por texto e saída no rodapé. No desktop ela permanece fixa e pode ser recolhida por um toggle; no estado compacto, títulos acessíveis preservam o significado dos ícones. No celular abre como gaveta por um botão identificado, usa fundo de proteção e fecha por botão, clique externo, tecla `Esc` ou seleção de destino. Usuários permanece como o último módulo gerencial; Ajuda e versões aparece em um grupo separado de suporte para os dois perfis, junto da versão atual. Tabelas futuras devem ter cabeçalhos persistentes quando densas e rolagem horizontal dentro do painel em telas estreitas. Badges exibem texto além da cor.

O calendário compartilhado por gerentes e profissionais apresenta sete colunas mesmo no celular, com números, contagens e marcadores compactos. A lista textual abaixo do mês concentra nome do profissional, horário, setor, categoria e status do plantão selecionado; atalhos de gestão aparecem somente para quem possui a permissão correspondente.

O painel gerencial usa o quadro `coverage-board` como ponto focal único: a faixa escura reúne apenas métricas operacionais reais, com trilhos verdes, vermelhos e âmbar para cobertura, descoberta e decisão. Alertas aparecem abaixo em cartões com contexto e uma ação textual clara. Em telas estreitas, todas as tabelas operacionais cedem lugar a cartões, preservando os mesmos dados e ações. Cada destino da sidebar mantém área de toque adequada.

### Forms and overlays

Rótulos ficam sempre visíveis. Erros aparecem junto ao campo com `aria-invalid` e `aria-describedby`. Formulários usam `novalidate` para uma experiência consistente. Senhas têm controle de exibição e autocomplete adequado. Alertas usam `role="status"` ou tratamento semântico equivalente. Nos módulos com CRUD, o botão “Adicionar” abre o formulário em um modal; edição reutiliza o mesmo componente, com título e ação contextualizados. No celular, o modal ocupa a altura disponível e mantém o corpo rolável entre cabeçalho e rodapé fixos.

O modal de nova versão é deliberado e não fecha por clique externo: o usuário escolhe continuar ou abrir o manual. O cabeçalho escuro o distingue de formulários, e os destaques usam uma lista curta com texto. A Central de Ajuda usa atalhos, acordeões e uma linha do tempo de versões para permitir leitura rápida no celular e consulta detalhada no desktop.

A Central de Notificações usa cartões com uma faixa suave para itens não lidos. Cada cartão contém mensagem, ações de leitura e o comprovante em uma grade compacta; no celular, os campos do comprovante formam uma coluna legível. A referência monoespaçada e o badge de situação reforçam a rastreabilidade sem criar uma estética separada do restante do sistema.

### Iconography

Ícones futuros devem usar traço simples de 1,5 a 2px, entre 16 e 20px, acompanhados por texto quando representam uma ação. O símbolo da marca é o único ícone preenchido recorrente.

### Motion

Transições de cor duram até 160ms e comunicam resposta. Carregamentos mantêm geometria. `prefers-reduced-motion` reduz animações e transições a duração mínima.

### Content and data visualization

A voz é direta e respeitosa. Estados dizem o que ocorreu e o próximo passo. Datas e horários futuros seguem `pt-BR`. Gráficos futuros precisam de legenda textual e alternativa tabular.

## Do's and Don'ts

- **Do:** usar as faixas de escala para reforçar estado ou sequência.
- **Do:** manter ações, permissões e mensagens próximas do contexto que as produz.
- **Don't:** transformar cada bloco em cartão ou pílula decorativa.
- **Don't:** esconder rótulos, erros ou ações essenciais atrás de ícones sem texto.
