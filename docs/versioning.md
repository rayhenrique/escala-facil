# Conferir a versão instalada

## Fonte canônica

`config/version.php`, campo `current`, identifica o **código instalado**.
O mesmo arquivo contém o histórico usado por Ajuda e novidades. Landing, login,
layouts autenticados e PWA usam esse registro. `APP_VERSION` continua nos
exemplos por compatibilidade, mas um valor antigo no `.env` não sobrepõe o código.
Não é necessário editar o `.env`; nenhuma configuração sensível é reescrita.

No bootstrap, somente os metadados de versão são recarregados do registro do
checkout. Isso evita que um `bootstrap/cache/config.php` antigo apresente a
versão anterior. As demais configurações continuam usando o cache do Laravel.
O diagnóstico acusa esse cache antigo para que seja reconstruído.

## O que foi corrigido

- APP_VERSION podia discordar do código e do histórico exibido em Ajuda.
- O release atualizava o exemplo APP_VERSION no README, mas deixava a versão
  visível no topo em 1.15.0.
- O deploy lia o destino do checkout **antes** do fast-forward; assim, podia
  anunciar a versão anterior e escrevê-la no `.env`.

`release:prepare` permanece o único comando que prepara lançamentos. Atualiza
registro, exemplos, topo do README, histórico e cache do service worker,
invalida o cache de configuração e atualiza o registro do processo atual.
Marcadores ausentes são detectados antes de modificar arquivos.

O deploy lê o destino no commit remoto, confirma o resultado após fast-forward,
limpa configuração antiga, otimiza caches e confere versões em CLI, landing,
login e service worker. O log final usa a versão efetivamente instalada.
Históricos e logs antigos não são reescritos: para auditá-los, compare o SHA
registrado com `git show SHA:config/version.php`.

## Conferência em produção (somente leitura)

Como usuário do site, na raiz do projeto:

```bash
git log -1 --oneline
php8.5 scripts/read-release-version.php config/version.php
php8.5 artisan version:check
php8.5 artisan version:check --json
curl -sSI https://escalafacil.kltecnologia.com/login
curl -sS https://escalafacil.kltecnologia.com/landing | grep 'app-version'
curl -sS https://escalafacil.kltecnologia.com/sw.js | head -n 1
```

`version:check` não modifica arquivos, caches ou usuários. Retorna código 0
quando os metadados conferem e 1 quando há divergência. O JSON informa a versão
instalada, runtime, registro, cache de configuração, exemplos, README, histórico
e service worker. Um aviso sobre APP_VERSION legada não é erro de instalação.
O leitor de arquivo também reconhece o formato antigo para auditoria de checkouts.

O HTML contém `<meta name="app-version" ...>` e o cabeçalho `X-App-Version`.
Compare ambos com o registro do checkout. O histórico de Ajuda marca a mesma
versão como atual; releases anteriores continuam disponíveis. O reconhecimento
de novidades pelo usuário não é modificado por essa conferência.

## Caches durante uma atualização autorizada

Se o diagnóstico apontar `config_cache`, reconstrua os caches no processo normal
de manutenção e depois confira novamente:

```bash
php8.5 artisan optimize:clear
php8.5 artisan optimize
php8.5 artisan reload
php8.5 artisan version:check --json
```

Se CLI e HTTP divergirem, confira o document root, o checkout atendido pelo
PHP-FPM e os caches do proxy/CDN. Em instalações com OPcache sem validação de
timestamps, recarregue também o PHP-FPM pelo gerenciamento do servidor; limpar
o cache do Artisan não substitui essa etapa. Os workers duradouros devem ser
recarregados durante a atualização.

HTML das telas usa `private, no-store`. O service worker usa rede para navegação
com fallback offline e não armazena HTML autenticado. A atualização do worker
ignora cache HTTP, ativa o worker novo e elimina caches estáticos dos releases
anteriores. Assets do build têm nomes com hash. Configure o servidor/CDN para
revalidar `/sw.js` e `/manifest.webmanifest`, sem cache longo nessas URLs.
Uma tela já aberta conserva seu DOM até recarregar; offline não confirma a
versão atualmente instalada no servidor.

Na primeira atualização que incorpora este ajuste, use o procedimento manual
atualizado de `deploy.md` ou garanta que o script novo foi obtido antes de
iniciar o deploy; um script antigo já em execução não é corrigido retroativamente.
Esta entrega apenas prepara e valida o código, sem executar deploy.
