<?php

namespace Tests\Feature;

use App\Support\ApplicationVersion;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ReleasePrepareTest extends TestCase
{
    /** @var array<string, string> */
    private array $backups = [];

    /** @var list<string> */
    private array $managedFiles = [];

    private string $originalBasePath;

    private string $fixturePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalBasePath = base_path();
        $this->fixturePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escala-release-test-'.uniqid();
        foreach (['config/version.php', '.env.example', 'README.md', 'deploy.md', 'versoes.md', 'public/sw.js'] as $relative) {
            $target = $this->fixturePath.DIRECTORY_SEPARATOR.$relative;
            File::ensureDirectoryExists(dirname($target));
            File::copy(base_path($relative), $target);
        }
        // Os testes de release jamais reescrevem o checkout ou copiam segredos reais.
        File::put($this->fixturePath.'/.env', "APP_VERSION=0.0.1\nAPP_KEY=fixture-only\n");
        File::ensureDirectoryExists($this->fixturePath.'/bootstrap/cache');
        $this->app->setBasePath($this->fixturePath);

        $this->managedFiles = [
            base_path('config/version.php'),
            base_path('.env.example'),
            base_path('README.md'),
            base_path('deploy.md'),
            base_path('versoes.md'),
            base_path('public/sw.js'),
        ];

        foreach ($this->managedFiles as $file) {
            $this->backups[$file] = File::get($file);
        }
    }

    protected function tearDown(): void
    {
        $this->app->setBasePath($this->originalBasePath);
        $resolved = realpath($this->fixturePath);
        $tempRoot = realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'escala-release-test-';
        if ($resolved && str_starts_with($resolved, $tempRoot)) {
            File::deleteDirectory($resolved);
        }

        config()->set('version', require base_path('config/version.php'));

        parent::tearDown();
    }

    public function test_rejects_invalid_semver_versions(): void
    {
        foreach (['1.16', 'v1.16.4', '1.16.4-beta', 'abc', '1.16.4.0', ''] as $invalid) {
            $this->artisan('release:prepare', ['version' => $invalid])
                ->assertFailed()
                ->expectsOutputToContain('Versão inválida');
        }

        $this->assertFilesUnchanged();
    }

    public function test_rejects_duplicate_version(): void
    {
        $current = $this->currentVersionFromFile();

        $this->artisan('release:prepare', ['version' => $current])
            ->assertFailed()
            ->expectsOutputToContain('já está registrada');

        $this->artisan('release:prepare', ['version' => '1.16.0'])
            ->assertFailed()
            ->expectsOutputToContain('já está registrada');

        $this->assertFilesUnchanged();
    }

    public function test_rejects_version_regression(): void
    {
        $this->artisan('release:prepare', ['version' => '0.0.1'])
            ->assertFailed()
            ->expectsOutputToContain('precisa ser maior');

        $this->assertFilesUnchanged();
    }

    public function test_prepares_new_patch_release_and_keeps_files_consistent(): void
    {
        $next = $this->nextPatchVersion();
        $envBefore = File::get(base_path('.env'));
        $existingReleases = $this->existingVersionsFromFile();

        $this->artisan('release:prepare', [
            'version' => $next,
            '--title' => 'Versão automatizada de teste',
            '--summary' => 'Resumo gerado pelo teste automatizado.',
            '--highlight' => ['Destaque um.', 'Destaque dois.'],
        ])
            ->assertSuccessful()
            ->expectsOutputToContain("Versão {$next} preparada")
            ->expectsOutputToContain('.env não foi alterado');

        // O .env de produção/local nunca é tocado pela preparação.
        $this->assertSame($envBefore, File::get(base_path('.env')));

        $this->assertSame($next, $this->currentVersionFromFile());
        $this->assertSame($next, $this->firstReleaseVersionFromFile());

        foreach ($existingReleases as $preserved) {
            $this->assertContains($preserved, $this->existingVersionsFromFile());
        }

        $this->assertStringContainsString("APP_VERSION={$next}", File::get(base_path('.env.example')));
        $this->assertStringContainsString("APP_VERSION={$next}", File::get(base_path('README.md')));
        $this->assertStringContainsString('**Versão atual:** `'.$next.'`', File::get(base_path('README.md')));
        $this->assertStringContainsString("APP_VERSION={$next}", File::get(base_path('deploy.md')));
        $this->assertStringContainsString("## {$next} —", File::get(base_path('versoes.md')));
        $this->assertStringContainsString('### Versão automatizada de teste', File::get(base_path('versoes.md')));
        $this->assertStringContainsString('- Destaque um.', File::get(base_path('versoes.md')));
        $this->assertStringContainsString("const CACHE_VERSION = 'escala-facil-static-v{$next}';", File::get(base_path('public/sw.js')));

        config()->set('version', require base_path('config/version.php'));
        $this->assertSame($next, app(ApplicationVersion::class)->current());
        $this->assertSame($next, app(ApplicationVersion::class)->currentRelease()['version']);

        $this->assertSafeAuthenticatedCacheStrategy(File::get(base_path('public/sw.js')));
    }

    public function test_version_files_are_consistent(): void
    {
        $current = $this->currentVersionFromFile();

        $this->assertSame($current, $this->envVersion(base_path('.env.example')));
        $this->assertSame($current, $this->envVersion(base_path('README.md')));
        $this->assertStringContainsString('**Versão atual:** `'.$current.'`', File::get(base_path('README.md')));
        $this->assertSame($current, $this->envVersion(base_path('deploy.md')));
        $this->assertSame($current, $this->firstReleaseVersionFromFile());
        $this->assertStringContainsString("## {$current} —", File::get(base_path('versoes.md')));
        $this->assertStringContainsString(
            "const CACHE_VERSION = 'escala-facil-static-v{$current}';",
            File::get(base_path('public/sw.js'))
        );

        config()->set('version', require base_path('config/version.php'));
        $this->assertSame($current, app(ApplicationVersion::class)->current());

        $this->assertSafeAuthenticatedCacheStrategy(File::get(base_path('public/sw.js')));
    }

    public function test_missing_readme_marker_fails_before_changing_release_files(): void
    {
        $path = base_path('README.md');
        File::put($path, preg_replace('/\*\*Versão atual:\*\* `[^`]+`/', 'Sem versão no topo', File::get($path)));
        $before = collect($this->managedFiles)->mapWithKeys(fn ($file) => [$file => File::get($file)]);
        $this->artisan('release:prepare', ['version' => $this->nextPatchVersion()])->assertFailed();
        foreach ($before as $file => $contents) {
            $this->assertSame($contents, File::get($file));
        }
    }

    public function test_preparation_invalidates_a_stale_config_cache_and_refreshes_the_running_registry(): void
    {
        $relativeCache = 'bootstrap/cache/version-test-'.uniqid().'.php';
        $cache = base_path($relativeCache);
        File::put($cache, "<?php return ['version' => ['current' => '0.0.1']];");
        $repository = Env::getRepository();
        $previous = $repository->get('APP_CONFIG_CACHE');
        $repository->set('APP_CONFIG_CACHE', $relativeCache);
        try {
            $next = $this->nextPatchVersion();
            $this->artisan('release:prepare', ['version' => $next])->assertSuccessful();
            $this->assertFileDoesNotExist($cache);
            $this->assertSame($next, app(ApplicationVersion::class)->current());
            $this->assertSame($next, app(ApplicationVersion::class)->currentRelease()['version']);
        } finally {
            $previous === null ? $repository->clear('APP_CONFIG_CACHE') : $repository->set('APP_CONFIG_CACHE', $previous);
            File::delete($cache);
        }
    }

    private function assertFilesUnchanged(): void
    {
        foreach ($this->backups as $file => $contents) {
            $this->assertSame($contents, File::get($file), "O arquivo {$file} não deveria ter sido alterado.");
        }
    }

    private function currentVersionFromFile(): string
    {
        $contents = File::get(base_path('config/version.php'));

        preg_match("/'current'\s*=>\s*'([^']+)'/", $contents, $matches);

        return $matches[1];
    }

    /** @return array<int, string> */
    private function existingVersionsFromFile(): array
    {
        preg_match_all("/'version'\s*=>\s*'([^']+)'/", File::get(base_path('config/version.php')), $matches);

        return $matches[1] ?? [];
    }

    private function firstReleaseVersionFromFile(): string
    {
        return $this->existingVersionsFromFile()[0];
    }

    private function envVersion(string $file): string
    {
        preg_match('/^APP_VERSION=(.*)$/m', File::get($file), $matches);

        return trim($matches[1] ?? '');
    }

    private function nextPatchVersion(): string
    {
        $parts = explode('.', $this->currentVersionFromFile());

        return $parts[0].'.'.$parts[1].'.'.((int) $parts[2] + 10);
    }

    private function assertSafeAuthenticatedCacheStrategy(string $serviceWorker): void
    {
        // Invalidação correta após cada atualização.
        $this->assertStringContainsString('const CACHE_VERSION = ', $serviceWorker);
        $this->assertStringContainsString("key.startsWith('escala-facil-static-')", $serviceWorker);

        // Estratégia segura: navegações usam rede com fallback offline, sem cache de HTML autenticado.
        $navigationStrategy = str($serviceWorker)
            ->after("if (request.mode === 'navigate')")
            ->before('const isStaticAsset')
            ->toString();

        $this->assertStringContainsString('caches.match(OFFLINE_URL)', $navigationStrategy);
        $this->assertStringNotContainsString('cache.put', $navigationStrategy);

        // Apenas assets estáticos são cacheados.
        $this->assertStringContainsString("url.pathname.startsWith('/build/assets/')", $serviceWorker);
        $this->assertStringContainsString("url.pathname.startsWith('/icons/')", $serviceWorker);
    }
}
