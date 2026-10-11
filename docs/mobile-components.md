# Biblioteca de componentes Mobile First

A biblioteca usa os tokens de `resources/css/app.css`, as regras de `DESIGN.md` e o contrato de `UX-CONTRACT.md`. Não implementa autorização, validação de domínio ou persistência. Cada Livewire mantém suas Policies, validações e Actions.

## Componentes canônicos

| Capacidade | Componente | Uso |
| --- | --- | --- |
| Janela de ação/formulário | `action-modal` | corpo rolável, cabeçalho e ações fora da rolagem |
| Bottom sheet responsivo | `bottom-sheet` | delega a `action-modal`; encosta na base no celular, centraliza a partir de sm |
| Confirmação | `confirmation-modal` | API existente, alertdialog e foco no cancelamento; delega a `action-modal` |
| Avisos | `toast`, `flash-notifications` | sucesso temporário, erro persistente; wrapper lê mensagens de sessão |
| Ações | `action-button` | primary, secondary e danger; wrappers primary/secondary/danger-button preservados |
| Ícones | `nav-icon` | única biblioteca SVG; ícones decorativos ocultos das tecnologias assistivas |
| Carregamento | `loading-state` | status textual; aceita wire:loading e wire:target |
| Lista vazia | `empty-state` | título, descrição, ícone opcional e slot de ações |
| Etapas | `step-progress` | lista de rótulos, etapa atual baseada em 1 e etapas concluídas identificadas |

## Formulário responsivo

```blade
<x-bottom-sheet :show="$showFormModal" id="sector-form"
    title="Adicionar setor" close-action="closeFormModal" submit-action="save"
    max-width="xl" track-changes initial-focus="#sector-name"
    return-focus="#sector-page-title">
    {{-- Campos existentes, com validação e rótulos associados --}}
    <x-slot:actions>
        <x-action-button @click="requestClose()" x-bind:disabled="busy">Cancelar</x-action-button>
        <x-action-button type="submit" variant="primary" target="save" busy-label="Salvando…">Criar setor</x-action-button>
    </x-slot:actions>
</x-bottom-sheet>
```

`action-modal` possui a mesma API e aceita `role`, `restore-focus` e `busy-action`. `heading` é opcional e deve conter o título com ID `{id}-title`. IDs devem ser únicos; o componente associa título e descrição automaticamente. `close-action` e `submit-action` são métodos Livewire existentes, definidos pela aplicação, não por entrada do usuário. Use botões nativos para ações e links para navegação. O slot `actions` permanece fora do corpo rolável; não aninhe outro form quando usar submit-action.

`track-changes` compara os valores locais iniciais com os campos atuais. Escape, Cancelar, X e fundo solicitam fechamento; se houver mudanças, é necessário escolher Continuar editando ou Descartar alterações. Reverter os campos aos valores iniciais remove o bloqueio. Não confunda esse recurso com proteção contra concorrência ou persistência no backend. O sucesso do servidor fecha normalmente. Atualizar/fechar o navegador não salva um rascunho.

O foco inicial usa o seletor configurado, o cancelamento ou o corpo. Alpine Focus fornece trap, fundo inerte e bloqueio de scroll; a restauração retorna ao acionador conectado ou ao seletor return-focus. O controlador remove sua retenção ao desmontar. Durante requisições do componente, o fechamento é bloqueado; falhas mantêm a janela e mostram erro de transporte. A validação recebe foco no primeiro campo inválido. Fluxos com busy-action mantêm o interceptor operacional existente, inclusive erros específicos e restauração de foco, sem duplicar controle de decisão. Não aninhe duas janelas modais simultâneas. trap-focus continua aceito pela confirmação por compatibilidade; a base sempre prende foco.

## Feedback e controles

```blade
<x-action-button icon="check-circle" variant="primary" target="save">Salvar</x-action-button>
<x-action-button icon="close" label="Fechar janela" @click="requestClose()" />
<x-loading-state wire:loading.flex.delay wire:target="gotoPage" label="Carregando registros…" />
<x-empty-state title="Nenhum registro encontrado." description="Altere os filtros." />
<x-step-progress :steps="['Selecionar', 'Revisar', 'Confirmar']" :current="2" />
<x-toast message="Alteração salva." />
<x-toast tone="error" message="Confira os dados e tente novamente." />
```

Botões com apenas ícone precisam de label; botões com texto preservam o rótulo visível. O estado ocupado sobrepõe rótulos numa grade para manter largura e usa disabled. Toasts de sucesso duram 5,5 segundos, pausam ao receber hover/foco e limpam o timer ao desmontar. Erros não desaparecem automaticamente. O aviso não deve ser o único local para erros de formulário ou consequência importante.

## Geometria e adoção gradual

As janelas têm altura máxima limitada por dvh e área segura superior; só o corpo rola, com overscroll contido. Ações permanecem no rodapé, com safe-area-inset-bottom. Margens laterais respeitam a área segura; desktop usa centralização. Movimento reduzido é aplicado pela regra global existente. A viewport autenticada usa interactive-widget=resizes-content para acomodar teclado nos navegadores que suportam a opção; o teste reduz a viewport, sem simular teclado físico de iOS. PWA não guarda formulários ou respostas autenticadas em cache.

Piloto: cadastro de setores (formulário e estado vazio); confirmações e flash-notifications evoluem suas implementações compartilhadas; realocação usa o indicador de seleção/revisão; Central de Notificações usa loading-state. Os modais próprios de Escalas, Usuários, calendário e lançamento de versão não foram reescritos. O modal antigo baseado em eventos open-modal permanece por compatibilidade, sem novos consumidores; futuras migrações devem usar a base canônica e seus testes.

## Validação

`MobileComponentsTest` cobre semântica, escape de conteúdo, autorização, validação, persistência e cancelamento. `tests/js/mobile-ui.test.mjs` cobre descarte, busy, restauração, timers e inicialização única. `npm run test:mobile-ui:browser` cobre Tab/Shift+Tab, Escape, fundo inerte, foco, descarte, validação, rodapé na viewport reduzida, leitura de toast, confirmação, axe, desktop/celular, movimento reduzido e wire:navigate. Executar apenas com banco local descartável: MOBILE_UI_EMAIL, MOBILE_UI_PASSWORD, MOBILE_UI_URL opcional (padrão 127.0.0.1:8797), MOBILE_UI_SCREENSHOTS, PLAYWRIGHT_MODULE, PLAYWRIGHT_CHANNEL e AXE_MODULE. O teste cria e exclui somente setores com nome único de QA; nunca apontar para produção.
