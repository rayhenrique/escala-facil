#!/usr/bin/env bash

set -Eeuo pipefail

project_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
php_bin="${PHP_BIN:-php8.5}"
composer_bin="${COMPOSER_BIN:-/usr/local/bin/composer}"

cd "$project_dir"

if [[ "$(id -u)" -eq 0 ]]; then
    echo "Execute o deploy com o usuário isolado do site, não como root." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "Arquivo .env não encontrado em $project_dir." >&2
    exit 1
fi

if [[ -n "$(git status --porcelain)" ]]; then
    echo "O repositório possui alterações locais. Revise ou guarde essas alterações antes do deploy." >&2
    git status --short
    exit 1
fi

git fetch origin main

if ! git merge-base --is-ancestor HEAD origin/main; then
    echo "A branch local divergiu de origin/main. O deploy foi interrompido antes da manutenção." >&2
    exit 1
fi

"$php_bin" artisan down --retry=60
trap '"$php_bin" artisan up >/dev/null 2>&1 || true' EXIT

git merge --ff-only origin/main
"$php_bin" "$composer_bin" install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build

release_version="$(grep '^APP_VERSION=' .env.example | cut -d= -f2-)"
sed -i "s/^APP_VERSION=.*/APP_VERSION=$release_version/" .env

chmod -R ug+rwX storage bootstrap/cache
"$php_bin" artisan migrate --force
"$php_bin" artisan optimize:clear
"$php_bin" artisan optimize
"$php_bin" artisan reload
"$php_bin" artisan up
trap - EXIT

app_url="$(grep '^APP_URL=' .env | cut -d= -f2- | tr -d '"' | sed 's:/*$::')"

curl --fail --silent --show-error "$app_url/up" >/dev/null
login_html="$(curl --fail --silent --show-error "$app_url/login")"

if grep -q 'livewire.min.js' <<<"$login_html"; then
    echo "Falha: a página ainda referencia a rota dinâmica do Livewire." >&2
    exit 1
fi

if ! grep -q 'window.livewireScriptConfig' <<<"$login_html"; then
    echo "Falha: a configuração do bundle do Livewire não foi encontrada." >&2
    exit 1
fi

echo "Deploy concluído com sucesso na versão $release_version."
