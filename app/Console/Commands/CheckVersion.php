<?php

namespace App\Console\Commands;

use App\Support\ApplicationVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CheckVersion extends Command
{
    protected $signature = 'version:check {--json : Exibe o diagnóstico em JSON}';

    protected $description = 'Confere a versão instalada e divergências de metadados, sem alterar arquivos ou usuários';

    public function handle(ApplicationVersion $versions): int
    {
        $registry = require config_path('version.php');
        $installed = $registry['current'];
        $cache = $this->laravel->getCachedConfigPath();
        $sources = [
            'runtime' => $versions->current(),
            'latest_release' => $registry['releases'][0]['version'] ?? null,
            'config_cache' => File::exists($cache) ? (require $cache)['version']['current'] ?? null : $installed,
            'env_example' => $this->extract(base_path('.env.example'), '/^APP_VERSION=([^\r\n]+)$/m'),
            'readme' => $this->extract(base_path('README.md'), '/\*\*Versão atual:\*\* `([^`]+)`/'),
            'readme_example' => $this->extract(base_path('README.md'), '/^APP_VERSION=([^\r\n]+)$/m'),
            'deploy_example' => $this->extract(base_path('deploy.md'), '/^APP_VERSION=([^\r\n]+)$/m'),
            'history' => $this->extract(base_path('versoes.md'), '/^## ([\d.]+) —/m'),
            'service_worker' => $this->extract(public_path('sw.js'), "/const CACHE_VERSION = 'escala-facil-static-v([^']+)';/"),
        ];
        $errors = [];
        foreach ($sources as $source => $value) {
            if ($value !== $installed) {
                $errors[] = "{$source} diverge da versão instalada.";
            }
        }
        $warnings = [];
        if (File::exists(base_path('.env')) && preg_match('/^APP_VERSION=/m', File::get(base_path('.env')))) {
            $warnings[] = 'APP_VERSION no .env é legada e não controla a versão instalada.';
        }
        $result = [
            'installed' => $installed,
            'canonical_source' => 'config/version.php',
            'config_cached' => File::exists($cache),
            'sources' => $sources,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        } else {
            $this->info("Versão instalada: {$installed} (config/version.php)");
            $this->table(['Fonte', 'Versão'], collect($sources)->map(fn ($value, $key) => [$key, $value ?? 'ausente'])->all());
            foreach ($errors as $error) {
                $this->error($error);
            }
            foreach ($warnings as $warning) {
                $this->warn($warning);
            }
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function extract(string $path, string $pattern): ?string
    {
        if (! File::exists($path) || ! preg_match($pattern, File::get($path), $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
