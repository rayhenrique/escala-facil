<?php

namespace App\Console\Commands;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

class PrepareRelease extends Command
{
    protected $signature = 'release:prepare
                            {version : Nova versão no formato X.Y.Z}
                            {--title= : Título da versão}
                            {--summary= : Resumo da versão}
                            {--highlight=* : Destaques da versão (pode repetir a opção)}';

    protected $description = 'Centraliza o versionamento e sincroniza sistema, documentação e PWA';

    public function handle(): int
    {
        $version = trim((string) $this->argument('version'));

        if (! preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            $this->error('Versão inválida. Use o formato SemVer X.Y.Z (ex.: 1.16.4).');

            return self::INVALID;
        }

        $basePath = base_path();
        $versionFile = $basePath.'/config/version.php';
        $envExampleFile = $basePath.'/.env.example';
        $readmeFile = $basePath.'/README.md';
        $deployFile = $basePath.'/deploy.md';
        $historyFile = $basePath.'/versoes.md';
        $serviceWorkerFile = $basePath.'/public/sw.js';

        foreach ([$versionFile, $envExampleFile, $readmeFile, $deployFile, $historyFile, $serviceWorkerFile] as $file) {
            if (! File::exists($file)) {
                $this->error("Arquivo necessário não encontrado: {$file}");

                return self::FAILURE;
            }
        }

        $current = $this->currentVersion($versionFile);
        $existing = $this->existingVersions($versionFile);

        if (in_array($version, $existing, true)) {
            $this->error("A versão {$version} já está registrada no histórico.");

            return self::INVALID;
        }

        if (version_compare($version, $current, '<=')) {
            $this->error("A versão {$version} precisa ser maior que a atual ({$current}).");

            return self::INVALID;
        }

        $title = trim((string) ($this->option('title') ?: "Versão {$version}"));
        $summary = trim((string) ($this->option('summary') ?: "Atualização de manutenção da versão {$version}."));
        /** @var array<int, string> $highlights */
        $highlights = collect((array) $this->option('highlight'))
            ->map(fn ($highlight) => trim((string) $highlight))
            ->filter()
            ->values()
            ->all();

        if ($highlights === []) {
            $highlights = ['Sincronização automatizada dos metadados de versão.'];
        }

        $date = CarbonImmutable::now('America/Maceio')->format('Y-m-d');
        $displayDate = CarbonImmutable::createFromFormat('Y-m-d', $date, 'America/Maceio')->format('d/m/Y');

        $this->updateVersionFile($versionFile, $current, $version, $date, $title, $summary, $highlights);
        $this->updateEnvLine($envExampleFile, $version);
        $this->updateEnvLine($readmeFile, $version);
        $this->updateEnvLine($deployFile, $version);
        $this->updateHistoryFile($historyFile, $version, $displayDate, $title, $highlights);
        $this->updateServiceWorker($serviceWorkerFile, $version);

        $this->info("Versão {$version} preparada com sucesso ({$displayDate}).");
        $this->info('Arquivos atualizados: config/version.php, .env.example, README.md, deploy.md, versoes.md, public/sw.js.');
        $this->info('O arquivo .env não foi alterado.');

        return self::SUCCESS;
    }

    private function currentVersion(string $versionFile): string
    {
        $contents = File::get($versionFile);

        if (! preg_match("/'current'\s*=>\s*env\(\s*'APP_VERSION'\s*,\s*'([^']+)'\s*\)/", $contents, $matches)) {
            throw new RuntimeException('Não foi possível identificar a versão atual em config/version.php.');
        }

        return $matches[1];
    }

    /** @return array<int, string> */
    private function existingVersions(string $versionFile): array
    {
        $contents = File::get($versionFile);

        preg_match_all("/'version'\s*=>\s*'([^']+)'/", $contents, $matches);

        return $matches[1] ?? [];
    }

    /**
     * @param  array<int, string>  $highlights
     */
    private function updateVersionFile(
        string $versionFile,
        string $current,
        string $version,
        string $date,
        string $title,
        string $summary,
        array $highlights,
    ): void {
        $contents = File::get($versionFile);

        $contents = preg_replace(
            "/'current'\s*=>\s*env\(\s*'APP_VERSION'\s*,\s*'".preg_quote($current, '/')."'\s*\)/",
            "'current' => env('APP_VERSION', '{$version}')",
            $contents,
            1
        ) ?? throw new RuntimeException('Falha ao atualizar a versão atual em config/version.php.');

        $highlightsCode = collect($highlights)
            ->map(fn (string $highlight) => '                '.var_export($highlight, true).',')
            ->implode("\n");

        $entry = "        [\n"
            ."            'version' => ".var_export($version, true).",\n"
            ."            'date' => ".var_export($date, true).",\n"
            ."            'title' => ".var_export($title, true).",\n"
            ."            'summary' => ".var_export($summary, true).",\n"
            ."            'highlights' => [\n"
            ."{$highlightsCode}\n"
            ."            ],\n"
            .'        ],';

        $anchor = "'releases' => [";
        $position = strpos($contents, $anchor);

        if ($position === false) {
            throw new RuntimeException('Bloco de lançamentos não encontrado em config/version.php.');
        }

        $insertAt = $position + strlen($anchor);
        $contents = substr($contents, 0, $insertAt)."\n".$entry.substr($contents, $insertAt);

        File::put($versionFile, $contents);
    }

    private function updateEnvLine(string $file, string $version): void
    {
        $contents = File::get($file);

        $updated = preg_replace('/^APP_VERSION=.*$/m', "APP_VERSION={$version}", $contents, 1);

        if ($updated === null || $updated === $contents && ! str_contains($contents, "APP_VERSION={$version}")) {
            throw new RuntimeException("Não foi possível atualizar APP_VERSION em {$file}.");
        }

        // Quando a versão já era a desejada, preg_replace não altera; mantém o arquivo intacto.
        if ($updated !== $contents) {
            File::put($file, $updated);
        }
    }

    /**
     * @param  array<int, string>  $highlights
     */
    private function updateHistoryFile(
        string $historyFile,
        string $version,
        string $displayDate,
        string $title,
        array $highlights,
    ): void {
        $contents = File::get($historyFile);

        $bullets = collect($highlights)
            ->map(fn (string $highlight) => '- '.$highlight)
            ->implode("\n");

        $entry = "## {$version} — {$displayDate}\n\n### {$title}\n\n{$bullets}\n\n";

        $position = strpos($contents, "\n## ");

        if ($position === false) {
            throw new RuntimeException('Histórico de versões não encontrado em versoes.md.');
        }

        $contents = substr($contents, 0, $position + 1).$entry.substr($contents, $position + 1);

        File::put($historyFile, $contents);
    }

    private function updateServiceWorker(string $serviceWorkerFile, string $version): void
    {
        $contents = File::get($serviceWorkerFile);

        $updated = preg_replace(
            "/const CACHE_VERSION = 'escala-facil-static-v[^']+';/",
            "const CACHE_VERSION = 'escala-facil-static-v{$version}';",
            $contents,
            1
        );

        if ($updated === null) {
            throw new RuntimeException('Falha ao atualizar o cache do service worker em public/sw.js.');
        }

        if ($updated !== $contents) {
            File::put($serviceWorkerFile, $updated);
        }
    }
}
