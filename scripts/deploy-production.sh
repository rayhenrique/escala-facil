#!/usr/bin/env bash
#
# Deploy de produção do Escala Fácil (Hostinger + CloudPanel).
#
# Uso (como usuário isolado do site, nunca como root):
#   bash scripts/deploy-production.sh
#
# Pré-requisito: a branch main deve estar com o CI verde (proteção de branch).
# O script NÃO executa rollback automático de migrations: em caso de falha
# após o início das migrations, ele preserva o backup e indica o procedimento
# manual de recuperação documentado em deploy.md (seções 17 e 18).

set -Eeuo pipefail

project_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
php_bin="${PHP_BIN:-php8.5}"
composer_bin="${COMPOSER_BIN:-/usr/local/bin/composer}"
backup_root="${DEPLOY_BACKUP_DIR:-$HOME/escala-facil-backups}"
backup_keep="${DEPLOY_BACKUP_KEEP:-10}"
min_disk_kb="${DEPLOY_MIN_DISK_KB:-1048576}"

merged=0
migrated=0
prev_head=""
prev_version=""
backup_file=""
build_backup_dir=""

log() {
    printf '[deploy %s] %s\n' "$(date '+%F %T')" "$*"
}

env_value() {
    grep "^$1=" .env | cut -d= -f2- | tr -d '"' | tr -d "'"
}

rollback_code() {
    log "Revertendo código e assets para ${prev_head}..."

    git reset --hard "$prev_head" || log "AVISO: git reset falhou; verifique manualmente com git status."

    "$php_bin" "$composer_bin" install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
        || log "AVISO: reinstall das dependências PHP falhou; execute manualmente."

    if [[ -n "$build_backup_dir" && -d "$build_backup_dir" ]]; then
        rm -rf public/build
        cp -a "$build_backup_dir" public/build \
            || log "AVISO: restauração de public/build falhou; execute npm ci && npm run build."
    fi

    "$php_bin" artisan optimize:clear >/dev/null 2>&1 || true
    "$php_bin" artisan optimize >/dev/null 2>&1 || true
}

fail() {
    echo "ERRO: $1" >&2
    echo "Diagnóstico: merged=${merged} migrated=${migrated} backup=${backup_file:-ausente}" >&2

    if [[ "$merged" -eq 1 && "$migrated" -eq 0 ]]; then
        rollback_code
    elif [[ "$migrated" -eq 1 ]]; then
        echo "As migrations já haviam iniciado: NENHUM rollback automático de banco foi executado." >&2
        echo "Para recuperar: restaure o backup ${backup_file:-'(indisponível)'} conforme deploy.md (seções 17 e 18)." >&2
    fi

    "$php_bin" artisan up >/dev/null 2>&1 || true
    trap - EXIT
    echo "Recuperação: consulte deploy.md (seções 17 e 18) e os logs em storage/logs/laravel.log." >&2
    exit 1
}

require_command() {
    command -v "$1" >/dev/null 2>&1 || fail "Pré-requisito ausente: comando '$1' não encontrado no PATH."
}

cd "$project_dir"

# ---------------------------------------------------------------------------
# FASE A — pré-voo (nenhuma alteração, sem manutenção)
# ---------------------------------------------------------------------------
log "FASE A: pré-voo (validações sem manutenção)."

[[ "$(id -u)" -ne 0 ]] || fail "Execute o deploy com o usuário isolado do site, não como root."
[[ -f .env ]] || fail "Arquivo .env não encontrado em $project_dir."

require_command git
require_command "$php_bin"
require_command node
require_command npm
require_command curl
require_command df
command -v mysqldump >/dev/null 2>&1 || command -v clpctl >/dev/null 2>&1 \
    || fail "Pré-requisito ausente: nem 'mysqldump' nem 'clpctl' disponíveis para backup do banco."

php_major="$("$php_bin" -r 'echo PHP_MAJOR_VERSION;')"
php_minor="$("$php_bin" -r 'echo PHP_MINOR_VERSION;')"
[[ "$php_major" -gt 8 || ( "$php_major" -eq 8 && "$php_minor" -ge 3 ) ]] \
    || fail "PHP 8.3 ou superior é exigido (encontrado: $("$php_bin" -v | head -n 1))."

node_major="$(node --version | sed 's/^v//' | cut -d. -f1)"
[[ "$node_major" -ge 20 ]] \
    || fail "Node.js 20 ou superior é exigido para o build (encontrado: $(node --version))."

free_kb="$(df -k --output=avail "$project_dir" | tail -n 1 | tr -d ' ')"
[[ "$free_kb" -ge "$min_disk_kb" ]] \
    || fail "Espaço insuficiente: ${free_kb} KiB livres, mínimo de ${min_disk_kb} KiB."

[[ -n "$(env_value DB_DATABASE)" ]] || fail "DB_DATABASE ausente no .env."
[[ -n "$(env_value DB_USERNAME)" ]] || fail "DB_USERNAME ausente no .env."
[[ -n "$(env_value APP_URL)" ]] || fail "APP_URL ausente no .env."

if [[ "$(env_value APP_ENV)" != "production" ]]; then
    log "AVISO: APP_ENV não é 'production' no .env; seguindo mesmo assim."
fi

if [[ "$(env_value APP_DEBUG)" == "true" ]]; then
    log "AVISO: APP_DEBUG=true no .env de produção; recomendado APP_DEBUG=false."
fi

if [[ -n "$(git status --porcelain)" ]]; then
    git status --short >&2
    fail "O repositório possui alterações locais. Revise ou guarde essas alterações antes do deploy."
fi

git fetch origin main || fail "Falha ao buscar origin/main."

git merge-base --is-ancestor HEAD origin/main \
    || fail "A branch local divergiu de origin/main. O deploy foi interrompido antes da manutenção."

# A versão de destino pertence ao commit remoto, não ao checkout anterior.
release_version="$(git show origin/main:config/version.php | "$php_bin" scripts/read-release-version.php)" \
    || fail "Não foi possível identificar a versão de destino em origin/main."

"$php_bin" "$composer_bin" validate --strict \
    || fail "composer validate --strict reprovou; corrija composer.json/composer.lock antes do deploy."

log "Pré-voo concluído. Versão de destino: $release_version."

# ---------------------------------------------------------------------------
# FASE B — manutenção (alterações + migrations + build)
# ---------------------------------------------------------------------------
prev_head="$(git rev-parse HEAD)"
prev_version="$("$php_bin" scripts/read-release-version.php config/version.php)"
log "FASE B: manutenção (versão atual: ${prev_version:-desconhecida})."

"$php_bin" artisan down --retry=60 || fail "Falha ao colocar a aplicação em manutenção."
trap '"$php_bin" artisan up >/dev/null 2>&1 || true' EXIT

git merge --ff-only origin/main || fail "Falha no fast-forward para origin/main."
merged=1
installed_version="$("$php_bin" scripts/read-release-version.php config/version.php)" \
    || fail "Versão instalada inválida após o fast-forward."
[[ "$installed_version" == "$release_version" ]] \
    || fail "A versão instalada diverge do commit de destino."
"$php_bin" artisan config:clear || fail "Falha ao invalidar o cache da configuração anterior."

if [[ -d public/build ]]; then
    build_backup_dir="$(mktemp -d)/build"
    cp -a public/build "$build_backup_dir" || fail "Falha ao copiar public/build para rollback."
fi

"$php_bin" "$composer_bin" install --no-dev --prefer-dist --optimize-autoloader --no-interaction \
    || fail "Falha na instalação das dependências PHP."
npm ci || fail "Falha em npm ci."
npm run build || fail "Falha em npm run build."
[[ -f public/build/manifest.json ]] \
    || fail "Build do frontend incompleto: public/build/manifest.json não gerado."

# APP_VERSION é legada: o deploy não reescreve configurações do .env.
chmod 600 .env || fail "Falha ao restringir permissões do .env."

chmod -R ug+rwX storage bootstrap/cache \
    || fail "Falha ao ajustar permissões de storage/bootstrap/cache."

log "Gerando backup do banco antes das migrations..."
mkdir -p "$backup_root" || fail "Falha ao criar o diretório de backups ($backup_root)."
chmod 700 "$backup_root" || fail "Falha ao restringir permissões do diretório de backups."

db_name="$(env_value DB_DATABASE)"
db_host="$(env_value DB_HOST)"
db_port="$(env_value DB_PORT)"
db_user="$(env_value DB_USERNAME)"
db_pass="$(env_value DB_PASSWORD)"
backup_file="$backup_root/escala_facil-$(date +%F-%H%M%S)-pre-${release_version}.sql.gz"

if command -v mysqldump >/dev/null 2>&1; then
    MYSQL_PWD="$db_pass" mysqldump -h "$db_host" -P "${db_port:-3306}" -u "$db_user" "$db_name" 2>/dev/null \
        | gzip > "$backup_file" \
        || fail "Falha no backup do banco com mysqldump."
else
    clpctl db:export --databaseName="$db_name" --file="$backup_file" \
        || fail "Falha no backup do banco com clpctl."
fi

chmod 600 "$backup_file" || fail "Falha ao restringir permissões do backup."
log "Backup salvo em $backup_file."

# shellcheck disable=SC2012
ls -1t "$backup_root"/escala_facil-*.sql.gz 2>/dev/null | tail -n +"$((backup_keep + 1))" | while IFS= read -r old; do
    rm -f "$old" && log "Backup antigo removido (retenção de $backup_keep): $old"
done

migrated=1
"$php_bin" artisan migrate --force \
    || fail "Falha nas migrations. Backup disponível em $backup_file; siga deploy.md (seções 17 e 18)."

"$php_bin" artisan optimize:clear || fail "Falha em optimize:clear."
"$php_bin" artisan optimize || fail "Falha em optimize."
"$php_bin" artisan version:check --json || fail "Metadados de versão inconsistentes após otimização."
"$php_bin" artisan reload || fail "Falha em reload dos workers."

"$php_bin" artisan up || fail "Falha ao reabrir a aplicação."
trap - EXIT

# ---------------------------------------------------------------------------
# FASE C — fumaça (sistema no ar, sem alterações)
# ---------------------------------------------------------------------------
log "FASE C: verificações de fumaça."

app_url="$(env_value APP_URL | sed 's:/*$::')"

curl --fail --silent --show-error "$app_url/up" >/dev/null \
    || fail "Smoke falhou: $app_url/up inacessível."
login_html="$(curl --fail --silent --show-error "$app_url/login")" \
    || fail "Smoke falhou: $app_url/login inacessível."

if grep -q 'livewire.min.js' <<<"$login_html"; then
    fail "Smoke falhou: a página ainda referencia a rota dinâmica do Livewire."
fi

if ! grep -q 'window.livewireScriptConfig' <<<"$login_html"; then
    fail "Smoke falhou: a configuração do bundle do Livewire não foi encontrada."
fi

if ! grep -Fq "<meta name=\"app-version\" content=\"$release_version\">" <<<"$login_html"; then
    fail "Smoke falhou: o login não exibe a versão instalada."
fi
landing_html="$(curl --fail --silent --show-error "$app_url/landing")" \
    || fail "Smoke falhou: landing inacessível."
if ! grep -Fq "<meta name=\"app-version\" content=\"$release_version\">" <<<"$landing_html"; then
    fail "Smoke falhou: a landing não exibe a versão instalada."
fi

manifest_json="$(curl --fail --silent --show-error "$app_url/manifest.webmanifest")" \
    || fail "Smoke falhou: manifesto da PWA inacessível."

if ! grep -q '"name": "Escala Fácil"' <<<"$manifest_json" || ! grep -q '"display": "standalone"' <<<"$manifest_json"; then
    fail "Smoke falhou: o manifesto da PWA está inválido."
fi

service_worker="$(curl --fail --silent --show-error "$app_url/sw.js")" \
    || fail "Smoke falhou: service worker inacessível."

if ! grep -Fq "const CACHE_VERSION = 'escala-facil-static-v${release_version}';" <<<"$service_worker" || ! grep -q '/offline.html' <<<"$service_worker"; then
    fail "Smoke falhou: o service worker da PWA está incompleto."
fi

if [[ -n "$build_backup_dir" ]]; then
    rm -rf "$(dirname "$build_backup_dir")" || true
fi

log "Deploy concluído com sucesso na versão $release_version (backup: $backup_file)."
