# Formulários e filtros no celular — 1.45.0

Escalas e Trocas reutilizam `operational-filters`, agora envolvido por `filter-panel`. Relatórios utiliza a mesma janela para setor, categoria, profissional e situação. Até 1023 px, ela se apresenta como bottom sheet; no desktop, permanece inline, com os controles nativos e filtragem imediata anteriores. O mês permanece no cabeçalho.

No celular, editar altera somente um rascunho. Limpar prepara valores padrão; Aplicar envia uma única atualização Livewire e aguarda a renderização da validação. Cancelar, fechar ou Escape preservam a consulta anterior. Chips exibem valores aplicados, datas em formato brasileiro e nomes das opções; remover um conserva os demais. Hooks e validações existentes continuam responsáveis por URL, paginação, enumerações e isolamento de setor. A visão operacional/histórica de Trocas conserva seus padrões ao limpar.

Usuários, Setores e Categorias mantêm pesquisas simples inline, com resumo removível e limpeza. Criação/edição de plantão, usuário e categoria passam a usar o modal responsivo canônico já usado por Setores. Os campos, nomes e regras das Actions/Form Requests permanecem os mesmos. A janela acompanha a viewport visual, conserva rodapé fora da rolagem, prende e restaura foco, bloqueia fundo e pede descarte explícito de entradas alteradas. A fonte mobile de 16 px evita zoom involuntário; zoom manual continua disponível.

A lista de profissionais na edição/criação de plantões é pesquisada no banco com debounce e até 50 resultados. Setor e categoria continuam combinados. A escolha atual recebe prioridade e permanece no resultado quando compatível; o texto da busca não se torna uma atribuição. O select continua nativo. A confirmação continua sujeita a compatibilidade, alertas e autorização anteriores. Seletores curtos permanecem nativos; os seletores pesquisáveis de solicitação de troca e realocação foram preservados.

## Verificação

- `php artisan test`: filtros combinados, URLs, paginação, isolamento, validação, autorização e regras operacionais; `MobileFormsFiltersTest` cobre limpeza de cadastros, limite SQL, seleção e formulários compartilhados.
- `npm run test:mobile-filters`: rascunho, cancelamento, aplicação única, falhas, IME e defaults vazios recebidos do Blade.
- `npm run test:filters:browser`: 320/390/1440 px, toque, filtro inválido, limpar/aplicar, chips, filtros combinados, refresh, paginação, retorno contextual e isolamento profissional.
- `npm run test:mobile-forms:browser`: formulários de Setores, Categorias, Usuários e Escalas; validação e foco, teclado, descarte, pesquisa nativa, viewport curta, axe e console.

Navegador: Playwright com Edge local, por ausência do Browser plugin. Os scripts exigem credenciais e fixture sintéticos fornecidos por variáveis `FILTERS_FIXTURE`, `FILTERS_QA_PASSWORD` e `FILTERS_URL`; não semeiam nem alteram produção. `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL`, `AXE_MODULE` e `FILTERS_SCREENSHOTS` permitem usar ferramentas já instaladas e evidência fora do repositório. Popups de controles nativos pertencem ao navegador/SO; emulação de viewport curta não substitui teste com teclado real no iOS.

Não foram criadas migrations nem alteradas regras de negócio, registros históricos ou configurações sensíveis. A versão foi preparada pelo comando de release existente.
