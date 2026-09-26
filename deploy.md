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
APP_TIMEZONE=America/Fortaleza
APP_VERSION=1.3.1

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

Abra o Tinker:

```bash
php8.5 artisan tinker
```

No prompt do Tinker, execute cada linha. A função `password` oculta a senha digitada:

```php
$senha = Laravel\Prompts\password('Senha do primeiro gerente');
$gerente = App\Models\User::create(['name' => 'Gerente de Plantões', 'email' => 'gerente@seudominio.com.br', 'password' => $senha, 'role' => 'manager']);
$gerente->id;
exit
```

Troque o nome e o e-mail pelo responsável real. Depois do primeiro login, os demais usuários podem ser cadastrados no módulo **Usuários**.

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

Antes de cada atualização, faça backup do banco pelo CloudPanel. Depois, como usuário do site:

```bash
cd ~/htdocs/escalafacil.kltecnologia.com
php8.5 artisan down --retry=60
git pull --ff-only origin main
php8.5 /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
release_version=$(grep '^APP_VERSION=' .env.example | cut -d= -f2-)
sed -i "s/^APP_VERSION=.*/APP_VERSION=$release_version/" .env
php8.5 artisan migrate --force
php8.5 artisan optimize:clear
php8.5 artisan optimize
php8.5 artisan reload
php8.5 artisan up
```

Verifique a versão e a saúde:

```bash
git log -1 --oneline
php8.5 artisan about --only=environment
curl -I https://escalafacil.kltecnologia.com/up
```

O comando `artisan reload` encerra de forma controlada workers que usam código antigo; o Supervisor os inicia novamente.

## 18. Backup e restauração

No CloudPanel, use a área de backup do servidor e mantenha cópias externas do banco e dos arquivos persistentes. Antes de uma atualização importante, o usuário `root` também pode exportar o banco:

```bash
clpctl db:export --databaseName=escala_facil --file=/root/escala_facil-$(date +%F-%H%M).sql.gz
```

Para restaurar um arquivo confirmado:

```bash
clpctl db:import --databaseName=escala_facil --file=/root/NOME-DO-BACKUP.sql.gz
```

A restauração substitui dados e deve ser usada somente após confirmar o arquivo, o banco de destino e a necessidade de rollback.

## 19. Recomendações de segurança

- mantenha `APP_DEBUG=false` em produção;
- não execute a aplicação como `root`;
- não versione nem exponha o `.env`;
- use senhas diferentes para SSH, usuário do site, banco e contas da aplicação;
- habilite autenticação em dois fatores no CloudPanel;
- restrinja as portas `22` e `8443` a IPs confiáveis ou use VPN;
- atualize o sistema operacional e o CloudPanel regularmente;
- configure SMTP com TLS para recuperação de senha;
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
