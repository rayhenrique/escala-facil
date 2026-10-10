<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Support\ApplicationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InstalledVersionTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_environment_and_stale_config_cannot_override_installed_version_on_any_screen(): void
    {
        $repository = Env::getRepository();
        $previous = $repository->get('APP_VERSION');
        $repository->set('APP_VERSION', '0.0.1');
        try {
            config()->set('version', ['current' => '1.15.0', 'releases' => []]);
            (new AppServiceProvider($this->app))->boot();
            $current = (require config_path('version.php'))['current'];
            $this->assertSame($current, app(ApplicationVersion::class)->currentRelease()['version']);
            foreach (['/', '/landing', '/login'] as $uri) {
                $this->get($uri)->assertOk()->assertSee('Versão '.$current)
                    ->assertSee('<meta name="app-version" content="'.$current.'">', false)
                    ->assertHeader('X-App-Version', $current)
                    ->assertDontSee('Versão 0.0.1')->assertDontSee('Versão 1.15.0');
            }
            foreach ([UserRole::Manager, UserRole::Professional] as $role) {
                $user = User::factory()->create(['role' => $role, 'last_seen_version' => '0.0.1']);
                $before = $user->fresh()->getAttributes();
                $uri = $role === UserRole::Manager ? '/manager/dashboard' : '/professional/dashboard';
                $this->actingAs($user)->get($uri)->assertOk()->assertSee('Versão '.$current)
                    ->assertHeader('X-App-Version', $current)
                    ->assertSee('<meta name="app-version" content="'.$current.'">', false);
                $this->actingAs($user)->get('/help')->assertOk()->assertSee('v'.$current);
                $this->assertSame($before, $user->fresh()->getAttributes());
            }
        } finally {
            $previous === null ? $repository->clear('APP_VERSION') : $repository->set('APP_VERSION', $previous);
        }
    }

    public function test_boot_from_a_real_stale_config_cache_reports_the_new_code_and_the_divergence(): void
    {
        $relativeCache = 'bootstrap/cache/installed-version-test-'.uniqid().'.php';
        $cache = base_path($relativeCache);
        $config = config()->all();
        $config['version'] = ['current' => '0.0.1', 'releases' => []];
        file_put_contents($cache, '<?php return '.var_export($config, true).';');
        try {
            $process = new Process([PHP_BINARY, 'artisan', 'version:check', '--json'], base_path(), ['APP_CONFIG_CACHE' => $relativeCache, 'APP_VERSION' => '0.0.2']);
            $process->run();
            $this->assertSame(1, $process->getExitCode());
        } finally {
            @unlink($cache);
        }
        $report = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $current = (require config_path('version.php'))['current'];
        $this->assertSame($current, $report['installed']);
        $this->assertSame($current, $report['sources']['runtime']);
        $this->assertSame('0.0.1', $report['sources']['config_cache']);
        $this->assertContains('config_cache diverge da versão instalada.', $report['errors']);
    }

    public function test_html_is_not_stored_and_pwa_update_does_not_reuse_an_old_worker(): void
    {
        foreach (['/', '/landing', '/login'] as $uri) {
            $response = $this->get($uri)->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $this->assertStringContainsString("updateViaCache: 'none'", file_get_contents(resource_path('js/app.js')));
    }

    public function test_diagnostic_is_read_only_and_reports_consistent_release_files(): void
    {
        $envHash = hash_file('sha256', base_path('.env'));
        $this->artisan('version:check')->assertSuccessful()->expectsOutputToContain('Versão instalada:');
        $this->assertSame($envHash, hash_file('sha256', base_path('.env')));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_source_reader_handles_new_and_old_registry_without_laravel_or_env(): void
    {
        foreach (["<?php return ['current' => '1.22.1'];", "<?php return ['current' => env('APP_VERSION', '1.22.0')];"] as $source) {
            $process = new Process([PHP_BINARY, 'scripts/read-release-version.php'], base_path(), ['APP_VERSION' => '0.0.1']);
            $process->setInput($source)->mustRun();
            $this->assertContains($process->getOutput(), ['1.22.1', '1.22.0']);
        }
    }

    public function test_diagnostic_detects_divergent_worker_and_documentation_without_rewriting_them(): void
    {
        $original = base_path();
        $fixture = sys_get_temp_dir().DIRECTORY_SEPARATOR.'escala-version-check-'.uniqid();
        $files = ['config/version.php', '.env.example', 'README.md', 'deploy.md', 'versoes.md', 'public/sw.js'];
        foreach ($files as $relative) {
            $target = $fixture.DIRECTORY_SEPARATOR.$relative;
            File::ensureDirectoryExists(dirname($target));
            copy(base_path($relative), $target);
        }
        file_put_contents($fixture.'/.env.example', "APP_VERSION=0.0.1\n");
        file_put_contents($fixture.'/public/sw.js', "const CACHE_VERSION = 'escala-facil-static-v0.0.2';\n");
        $this->app->setBasePath($fixture);
        try {
            $before = array_map(fn ($file) => hash_file('sha256', base_path($file)), $files);
            $this->artisan('version:check')->assertFailed()
                ->expectsOutputToContain('env_example diverge')
                ->expectsOutputToContain('service_worker diverge');
            $this->assertSame($before, array_map(fn ($file) => hash_file('sha256', base_path($file)), $files));
        } finally {
            $this->app->setBasePath($original);
            $resolved = realpath($fixture);
            if ($resolved && str_starts_with($resolved, realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR.'escala-version-check-')) {
                File::deleteDirectory($resolved);
            }
        }
    }
}
