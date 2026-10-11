# Inicialização do aplicativo e modais

O aplicativo utiliza o ESM de Livewire, que inclui Alpine, e apenas `@livewireScriptConfig` nos layouts. Não acrescente `@livewireScripts`, CDN de Alpine ou outro `Livewire.start()`.

A importação ESM escreve nos objetos globais antes de `start()`. Por isso `bootLivewire` protege a importação e todos os registros de inicialização por documento. Chamadas concorrentes compartilham a mesma promessa. Falhas são relatadas e exigem atualização deliberada da página, sem tentar iniciar outro runtime parcialmente carregado.

Na versão de Livewire instalada, a navegação compara os scripts do head e pode carregar um novo nome de arquivo Vite na mesma página. A detecção de atualização pelo `data-navigate-track` cobre mudança da query string na mesma URI, mas não a troca do nome com hash. Uma segunda importação sobrescreve Livewire/Alpine e pode interromper os controles dos componentes. Isso foi reproduzido com o bundle 1.32.1, navegação para Escalas e um segundo nome de arquivo: ambos os avisos apareceram e o formulário não respondeu.

Quando um novo bundle encontra outro ativo, recarrega o destino atual antes de importar os runtimes. A URL e seus filtros são mantidos; alterações de formulário ainda não enviadas não são restauradas. A proteção também recupera uma aba aberta em uma versão anterior, sem o marcador de inicialização. Foco, fundo inerte e bloqueio somente durante requisições dos modais permanecem existentes.

As animações são carregadas somente para a landing, independentemente da inicialização operacional. Falha no download dessa melhoria mantém o conteúdo visível e não bloqueia o login ou os módulos de gestão.

O aviso de `beforeinstallprompt.preventDefault()` é esperado enquanto o aplicativo guarda a oferta para o botão de instalação. O método `prompt()` é chamado pelo clique do usuário, não automaticamente. Ele não é a causa dos avisos de duplicação. A metatag padrão `mobile-web-app-capable` acompanha a tag Apple para compatibilidade com iOS.

## Conferência após instalação

Confira `php artisan version:check --json`, a versão no rodapé e a metatag `app-version`. No navegador, atualize a página uma vez, navegue entre Dashboard, Relatórios, Escalas e Trocas e abra/feche os modais. Não devem aparecer avisos de múltiplas instâncias de Livewire/Alpine. Se persistirem, verifique scripts adicionais de terceiros ou um build antigo servido pela infraestrutura, sem desabilitar os controles de foco dos modais.

Regressões automatizadas: `npm run test:runtime`, teste PHP `FrontendRuntimeTest` e `npm run test:runtime:browser`. O teste de navegador exige banco descartável, `RUNTIME_QA_EMAIL`, `RUNTIME_QA_PASSWORD` e `RUNTIME_QA_SWAP` (pedido pendente válido). Aceita `RUNTIME_URL`, `PLAYWRIGHT_MODULE`, `PLAYWRIGHT_CHANNEL`, `AXE_MODULE` e `RUNTIME_SCREENSHOTS`. Opcionalmente `RUNTIME_LEGACY_BUNDLE` aponta para o bundle compilado anterior para testar uma aba legada de verdade. Exercita erros de validação e abertura da edição sem criar/alterar plantão nem decidir o pedido. Cobre navegação, identidade dos runtimes, cancelar/Escape, revisão de troca, celular, desktop, service worker, acessibilidade e troca simulada do nome do bundle com filtros na URL.
