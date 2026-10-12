# Deploy do Escala Fácil na Hostinger com CloudPanel

Este guia instala o Escala Fácil no ambiente já criado para o projeto:

| Item | Valor |
| --- | --- |
| VPS | Hostinger |
| Painel | CloudPanel |
| IP | `72.60.142.2` |
| Domínio | `escalafacil.kltecnologia.com` |
| Usuário do site | `kltecnologia-escalafacil` |
| Diretório | `/home/kltecnologia-escalafacil/htdocs/escalafacil.kltecnologia.com` |
| Aplicação | Laravel 13 |
| PHP | 8.5 |
| Repositório | `https://github.com/rayhenrique/escala-facil.git` |

Não copie senhas para este arquivo, para o GitHub ou para comandos que ficarão no histórico do terminal. Guarde a senha do site e a senha do banco em um gerenciador de senhas.

## 1. Confirmar o site no CloudPanel

O site deve ter sido criado em **Sites → Adicionar site → Criar site PHP** com estes valores:

- aplicação: **Laravel 13**;
- domínio: `escalafacil.kltecnologia.com`;
- PHP: **8.5**;
- usuário do site: `kltecnologia-escalafacil`.

O template Laravel do CloudPanel já aponta o Nginx para a pasta `public`. Nunca altere o document root para a raiz do projeto, pois isso poderia expor o arquivo `.env` e outros arquivos internos.

## 2. Apontar o DNS para a VPS

No provedor que administra o DNS de `kltecnologia.com`, crie ou ajuste este registro:

| Tipo | Nome | Destino | TTL |
| --- | --- | --- | --- |
| A | `escalafacil` | `72.60.142.2` | padrão |

Como o sistema usa um subdomínio, o nome do registro é `escalafacil`; não é necessário criar registros `@` ou `www` para esta instalação.

Verifique a propagação no computador local:

```powershell
Resolve-DnsName escalafacil.kltecnologia.com -Type A
```

O resultado precisa mostrar `72.60.142.2`. A propagação de DNS pode levar até 24 horas.

## 3. Criar o banco no CloudPanel

Abra o site no CloudPanel e acesse **Bancos de dados → Novo banco de dados**. Preencha:

- nome do banco: `escala_facil`;
- usuário do banco: `escala_facil`;
- senha: gere uma senha forte e exclusiva.

Salve a senha. Ela será usada apenas no `.env` da VPS e não deve ser enviada ao GitHub.

## 4. Acessar a VPS e entrar como usuário do site

No PowerShell local:

```powershell
ssh root@72.60.142.2
```

Na VPS, troque para o usuário isolado do site:

```bash
su - kltecnologia-escalafacil
cd ~/htdocs/escalafacil.kltecnologia.com
pwd
ls -la
```

O `pwd` deve exibir exatamente:

```text
/home/kltecnologia-escalafacil/htdocs/escalafacil.kltecnologia.com
```

## 5. Baixar o projeto do GitHub

Na primeira instalação, o CloudPanel costuma criar uma pasta `public` vazia. O comando `rmdir` abaixo só a remove se estiver realmente vazia:

```bash
cd ~/htdocs/escalafacil.kltecnologia.com
rmdir public
git clone https://github.com/rayhenrique/escala-facil.git .
git status
```

Se `rmdir public` informar que a pasta não está vazia, pare e confira seu conteúdo com `ls -la public`. Não use `rm -rf` sem confirmar o diretório e os arquivos.

Ao final, `git status` deve indicar a branch `main` sem modificações locais.

## 6. Verificar PHP, Composer e extensões

O CloudPanel disponibiliza a versão selecionada com o comando `php8.5`:

```bash
php8.5 -v
php8.5 /usr/local/bin/composer --version
php8.5 -m | grep -E 'ctype|curl|dom|fileinfo|filter|mbstring|openssl|pdo_mysql|session|tokenizer|xml'
```

O Laravel 13 requer PHP 8.3 ou superior. Para este site, todos os comandos Artisan e Composer devem usar `php8.5` explicitamente.

## 7. Instalar as dependências PHP

Dentro da pasta do projeto:

```bash
cd ~/htdocs/escalafacil.kltecnologia.com
php8.5 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
```

Não execute `composer update` na VPS. O comando `install` respeita as versões testadas e registradas em `composer.lock`.

## 8. Configurar o ambiente de produção

Crie o `.env` e restrinja sua leitura ao usuário do site:

```bash
cp .env.example .env
chmod 600 .env
nano .env
```

Use esta base e substitua `SENHA_FORTE_DO_BANCO` pela senha criada no CloudPanel:

```dotenv
APP_NAME="Escala Fácil"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://escalafacil.kltecnologia.com
APP_TIMEZONE=America/Maceio
APP_VERSION=1.39.0

APP_LOCALE=pt_BR
APP_FALLBACK_LOCALE=pt_BR
APP_FAKER_LOCALE=pt_BR

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=escala_facil
DB_USERNAME=escala_facil
DB_PASSWORD="SENHA_FORTE_DO_BANCO"
DB_ENGINE=InnoDB

SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

FILESYSTEM_DISK=local

MAIL_MAILER=log
MAIL_FROM_ADDRESS="nao-responda@escalafacil.kltecnologia.com"
MAIL_FROM_NAME="${APP_NAME}"

# Observabilidade opcional (Sentry). Vazio = desativado, com tudo em logs locais.
SENTRY_DSN=
```

Enquanto `MAIL_MAILER=log`, mensagens de recuperação de senha são escritas nos logs e não são entregues por e-mail. Configure um serviço SMTP antes de liberar recuperação de senha para usuários reais.

Gere a chave da aplicação e confirme sem revelar o conteúdo do `.env`:

```bash
php8.5 artisan key:generate --force
php8.5 artisan about --only=environment
```

## 9. Gerar os arquivos do frontend

Verifique se Node.js e npm estão disponíveis:

```bash
node --version
npm --version
```

O projeto foi validado com Node.js 24, e também pode ser compilado com uma versão LTS compatível com o Vite 7. Se o servidor já possui Node.js compatível:

```bash
npm ci
npm run build
```

O build deve criar `public/build/manifest.json`. Confirme:

```bash
test -f public/build/manifest.json && echo "Build do frontend concluído"
```

O JavaScript do Livewire é incorporado ao bundle do Vite. Essa estratégia evita que regras de arquivos estáticos do Nginx no CloudPanel bloqueiem a rota dinâmica do Livewire. Por isso, execute `npm run build` sempre depois de `composer install` ou de qualquer atualização do Livewire.

Se `node` não existir, instale o NVM e o Node.js 24 no usuário do site:

```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.8/install.sh | bash
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && . "$NVM_DIR/nvm.sh"
command -v nvm
nvm install 24
nvm alias default 24
node --version
npm --version
npm ci
npm run build
```

O comando `command -v nvm` deve imprimir `nvm`. Se não imprimir, encerre a sessão SSH, entre novamente como usuário do site e repita a partir de `nvm install 24`.

## 10. Preparar diretórios e banco de dados

Garanta escrita apenas onde o Laravel precisa e crie o link público de armazenamento:

```bash
chmod -R ug+rwX storage bootstrap/cache
php8.5 artisan storage:link
```

Execute as migrations de produção:

```bash
php8.5 artisan migrate --force
php8.5 artisan migrate:status
```

Não execute `php artisan migrate:fresh`, pois ele apaga todas as tabelas. Não execute `php artisan demo:data seed` em produção. A massa fictícia é exclusiva para testes.

## 11. Criar o primeiro gerente

Use o comando interativo e informe a senha nos prompts ocultos:

```bash
php8.5 artisan user:create-manager --name="Nome do responsável" --email="responsavel@exemplo.com"
```

A senha deve ter pelo menos 12 caracteres, letras maiúsculas e minúsculas, número e símbolo. Ela não é passada na linha de comando nem gravada no histórico do terminal. Depois do primeiro login, os demais usuários podem ser cadastrados no módulo **Usuários**.

## 12. Otimizar a aplicação

Com o `.env` pronto e as migrations concluídas:

```bash
php8.5 artisan optimize
php8.5 artisan about --only=environment
```

Se alterar qualquer valor do `.env` depois disso, execute novamente:

```bash
php8.5 artisan optimize:clear
php8.5 artisan optimize
```

## 13. Configurar o agendador

Como usuário `kltecnologia-escalafacil`, abra o crontab:

```bash
crontab -e
```

Adicione uma única linha:

```cron
* * * * * cd /home/kltecnologia-escalafacil/htdocs/escalafacil.kltecnologia.com && /usr/bin/php8.5 artisan schedule:run >> /dev/null 2>&1
```

Também é possível cadastrar o mesmo comando na aba **Cron Jobs** do site no CloudPanel.

## 14. Configurar o worker da fila

As notificações em banco desta versão são gravadas durante a requisição, mas a fila deve permanecer ativa para tarefas assíncronas e futuras integrações. Saia do usuário do site para voltar ao `root`:

```bash
exit
command -v php8.5
apt-get update
apt-get install -y supervisor
nano /etc/supervisor/conf.d/escala-facil-worker.conf
```

Adicione:

```ini
[program:escala-facil-worker]
process_name=%(program_name)s_%(process_num)02d
directory=/home/kltecnologia-escalafacil/htdocs/escalafacil.kltecnologia.com
command=/usr/bin/php8.5 artisan queue:work database --sleep=3 --tries=3 --timeout=120 --max-time=3600
user=kltecnologia-escalafacil
numprocs=1
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
redirect_stderr=true
stdout_logfile=/home/kltecnologia-escalafacil/htdocs/escalafacil.kltecnologia.com/storage/logs/queue-worker.log
stopwaitsecs=130
```

Se `command -v php8.5` retornar um caminho diferente de `/usr/bin/php8.5`, use o caminho retornado nos arquivos do Supervisor e do cron.

Ative e verifique:

```bash
supervisorctl reread
supervisorctl update
supervisorctl start escala-facil-worker:*
supervisorctl status escala-facil-worker:*
```

O estado esperado é `RUNNING`.

## 15. Instalar HTTPS

Primeiro confirme que o DNS já aponta para `72.60.142.2`. Depois, no CloudPanel:

1. abra **Sites → escalafacil.kltecnologia.com**;
2. acesse **SSL/TLS**;
3. selecione **Ações → Novo certificado Let's Encrypt**;
4. informe `escalafacil.kltecnologia.com`;
5. selecione **Criar e instalar**.

O CloudPanel cria inicialmente um certificado autoassinado. O certificado Let's Encrypt válido só pode ser emitido depois que o DNS estiver correto.

## 16. Validar a instalação

Como usuário do site:

```bash
su - kltecnologia-escalafacil
cd ~/htdocs/escalafacil.kltecnologia.com
php8.5 artisan about
php8.5 artisan migrate:status
php8.5 artisan route:list --path=login
curl -I https://escalafacil.kltecnologia.com/up
curl -I https://escalafacil.kltecnologia.com/login
```

Os dois comandos `curl` devem responder com HTTP `200` ou um redirecionamento HTTPS esperado. No navegador, valide:

1. login do primeiro gerente;
2. dashboard e sidebar;
3. criação de setor, categoria e profissional;
4. criação de plantão;
5. solicitação, aceite e aprovação de troca;
6. Central de Notificações e comprovantes;
7. layout em celular e desktop.

Confira os logs se houver erro:

```bash
tail -n 100 storage/logs/laravel.log
tail -n 100 storage/logs/queue-worker.log
```

Os logs do Nginx e PHP também ficam disponíveis na aba **Logs** do site no CloudPanel.

## 17. Atualizar o sistema depois de novas versões

Só execute o deploy com o CI verde na `main` (proteção de branch documentada no `README`). O fluxo recomendado usa o script versionado em três fases — pré-voo sem manutenção, manutenção e verificações de fumaça:

```bash
cd ~/htdocs/escalafacil.kltecnologia.com
bash scripts/deploy-production.sh
```

Na **fase de pré-voo** (antes de qualquer alteração ou manutenção), o script valida: execução como usuário do site (nunca `root`), comandos exigidos (`git`, `php8.5`, `node`, `npm`, `curl`, `mysqldump` ou `clpctl`), PHP 8.3+ e Node.js 20+, espaço livre mínimo de 1 GiB (`DEPLOY_MIN_DISK_KB`), chaves obrigatórias do `.env`, árvore Git limpa, fast-forward possível para `origin/main` e `composer validate --strict`. A versão de destino é lida de `config/version.php` no commit remoto após o fetch. Qualquer falha aqui interrompe o processo sem tocar no sistema.

Na **fase de manutenção**, o script registra o commit atual para rollback, entra em manutenção, atualiza a branch somente por fast-forward, confirma a versão do código instalado e limpa o cache antigo de configuração. Instala dependências, compila o frontend (com verificação de `public/build/manifest.json`), preserva o conteúdo do `.env`, gera **backup automático do banco antes das migrations** (ver seção 18), executa `migrate --force`, otimiza caches, confere `version:check --json`, recarrega workers e reabre o sistema.

Na **fase de fumaça** (sistema já no ar), o script confirma `/up`, o bundle do Livewire (sem `livewire.min.js`, com `window.livewireScriptConfig`), a versão em `/login` e `/landing`, o manifesto e a versão do service worker da PWA.

Em caso de falha em etapa crítica, o script interrompe com diagnóstico (`merged`, `migrated`, caminho do backup) e reabre o sistema. Falhas antes das migrations revertem código e assets automaticamente; **migrations nunca são revertidas automaticamente** (`migrate:rollback` não existe no script). O procedimento de recuperação está na seção 18.

Para executar as mesmas etapas manualmente, como usuário do site (incluindo o backup obrigatório antes de migrar):

```bash
set -Eeuo pipefail
cd ~/htdocs/escalafacil.kltecnologia.com
test -z "$(git status --porcelain)"
git fetch origin main
git merge-base --is-ancestor HEAD origin/main
php8.5 /usr/local/bin/composer validate --strict
df -k . | awk 'NR==2 {exit ($4 < 1048576)}'
git merge --ff-only origin/main
php8.5 artisan down --retry=60
trap 'php8.5 artisan up >/dev/null 2>&1 || true' EXIT
php8.5 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
test -f public/build/manifest.json
release_version=$(php8.5 scripts/read-release-version.php config/version.php)
php8.5 artisan config:clear
chmod 600 .env
MYSQL_PWD="$(grep '^DB_PASSWORD=' .env | cut -d= -f2- | tr -d '"')" mysqldump -h 127.0.0.1 -P 3306 -u escala_facil escala_facil | gzip > ~/escala-facil-backups/escala_facil-$(date +%F-%H%M)-pre-${release_version}.sql.gz
chmod 600 ~/escala-facil-backups/escala_facil-$(date +%F-%H%M)-pre-${release_version}.sql.gz
php8.5 artisan migrate --force
php8.5 artisan optimize:clear
php8.5 artisan optimize
php8.5 artisan version:check --json
php8.5 artisan reload
php8.5 artisan up
trap - EXIT
```

Verifique a versão e a saúde:

```bash
git log -1 --oneline
php8.5 artisan about --only=environment
php8.5 artisan version:check --json
curl -I https://escalafacil.kltecnologia.com/up
curl -I https://escalafacil.kltecnologia.com/manifest.webmanifest
curl -I https://escalafacil.kltecnologia.com/sw.js
```

O comando `artisan reload` encerra de forma controlada workers que usam código antigo; o Supervisor os inicia novamente. A conferência completa de versão, HTML e PWA está em [`docs/versioning.md`](docs/versioning.md). `APP_VERSION` no `.env` não precisa ser sincronizada. Logs antigos de deploy registram o que ocorreu na ocasião e não são reescritos.

## 18. Backup, rollback e recuperação

A cada deploy, o script gera automaticamente um backup do banco **antes** das migrations em `~/escala-facil-backups/` (fora do repositório, para nunca sujar a árvore Git), com o nome `escala_facil-AAAA-MM-DD-HHMM-pre-X.Y.Z.sql.gz`. O diretório usa `chmod 700` e cada arquivo `chmod 600`. A retenção mantém os 10 backups mais recentes e remove os antigos automaticamente. Ajustes via ambiente:

```bash
DEPLOY_BACKUP_DIR=~/meus-backups DEPLOY_BACKUP_KEEP=20 DEPLOY_MIN_DISK_KB=2097152 bash scripts/deploy-production.sh
```

No CloudPanel, use também a área de backup do servidor e mantenha cópias externas do banco e dos arquivos persistentes. Antes de uma atualização importante, o usuário `root` também pode exportar o banco:

```bash
clpctl db:export --databaseName=escala_facil --file=/root/escala_facil-$(date +%F-%H%M).sql.gz
```

Para restaurar um backup com `mysqldump` (substitua o arquivo pelo backup confirmado do deploy):

```bash
gunzip -c ~/escala-facil-backups/NOME-DO-BACKUP.sql.gz | mysql -h 127.0.0.1 -P 3306 -u escala_facil -p escala_facil
```

Ou com o CloudPanel:

```bash
clpctl db:import --databaseName=escala_facil --file=/root/NOME-DO-BACKUP.sql.gz
```

A restauração substitui dados e deve ser usada somente após confirmar o arquivo, o banco de destino e a necessidade de rollback.

**Rollback de código e assets (sem tocar no banco):** se o deploy falhar antes das migrations, o próprio script já reverte o commit (`git reset --hard`), reinstala as dependências do `composer.lock` anterior, restaura `public/build` e reconstrói caches. A versão acompanha o checkout revertido, sem editar o `.env`. Manualmente:

```bash
cd ~/htdocs/escalafacil.kltecnologia.com
git log -1 --oneline
git reset --hard COMMIT-ANTERIOR
php8.5 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build
php8.5 artisan optimize:clear && php8.5 artisan optimize
php8.5 artisan up
```

**Migrations nunca são revertidas automaticamente.** O script não contém `migrate:rollback`, `migrate:fresh`, `migrate:refresh` nem `db:wipe`: reversões destrutivas apagariam dados de produção. Se uma migration falhar após o backup, o caminho é restaurar o backup confirmado acima e investigar a causa antes de nova tentativa.

**Checklist de recuperação após falha crítica:**

1. anote o diagnóstico impresso pelo script (`merged`, `migrated`, caminho do backup);
2. confirme que o sistema saiu da manutenção: `php8.5 artisan about --only=environment` e `curl -I https://escalafacil.kltecnologia.com/up`;
3. se `migrated=0`, o código já foi revertido; confira `git log -1 --oneline` e a versão exibida no rodapé;
4. se `migrated=1`, não tente `migrate:rollback`; restaure o backup confirmado e valide `php8.5 artisan migrate:status`;
5. confira os logs: `tail -n 100 storage/logs/laravel.log` e a aba **Logs** do CloudPanel;
6. só inicie um novo deploy após causa corrigida e CI verde na `main`.

## 19. Recomendações de segurança

- mantenha `APP_DEBUG=false` em produção;
- não execute a aplicação como `root`;
- não versione nem exponha o `.env`;
- use senhas diferentes para SSH, usuário do site, banco e contas da aplicação;
- habilite autenticação em dois fatores no CloudPanel;
- restrinja as portas `22` e `8443` a IPs confiáveis ou use VPN;
- atualize o sistema operacional e o CloudPanel regularmente;
- configure SMTP com TLS para recuperação de senha;
- configure `SENTRY_DSN` somente se usar monitoramento externo; vazio mantém erros apenas nos logs locais;
- teste restaurações de backup periodicamente;
- não instale dados de demonstração no ambiente de produção.

## Referências oficiais

- [CloudPanel — Laravel](https://www.cloudpanel.io/docs/v2/php/applications/laravel/)
- [CloudPanel — certificados SSL/TLS](https://www.cloudpanel.io/docs/v2/frontend-area/tls/)
- [CloudPanel — Cron Jobs](https://www.cloudpanel.io/docs/v2/frontend-area/cron-jobs/)
- [CloudPanel — comandos de root e banco](https://www.cloudpanel.io/docs/v2/cloudpanel-cli/root-user-commands/)
- [CloudPanel — boas práticas de segurança](https://www.cloudpanel.io/docs/v2/guides/best-practices/security/)
- [Laravel 13 — deployment](https://laravel.com/docs/13.x/deployment)
- [Hostinger — apontar domínio para VPS](https://www.hostinger.com/support/1583227-how-to-point-a-domain-to-your-vps-at-hostinger/)
- [NVM — instalação oficial](https://github.com/nvm-sh/nvm#installing-and-updating)

### Convites de profissionais (1.31.0)

Antes de liberar convites, aplique a migração da versão no fluxo normal, configure `APP_URL` HTTPS e `INVITATION_MAILER=smtp` (ou mailer real existente) com os parâmetros `MAIL_*`. Preserve as credenciais locais. Mantenha o worker supervisionado para `QUEUE_CONNECTION=database` e verifique `failed_jobs`; `sync` envia após commit na requisição. Transporte `log`/`null` e fallback para log são recusados. Detalhes: [convites](docs/account-invitations.md). Esta alteração de código não executou deploy.
