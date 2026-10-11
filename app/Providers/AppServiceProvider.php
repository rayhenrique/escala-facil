<?php

namespace App\Providers;

use App\Support\OperationalAuditContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(OperationalAuditContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Metadados do release são pequenos e devem acompanhar o checkout,
        // mesmo quando um config.php antigo foi preservado na atualização.
        config()->set('version', require config_path('version.php'));
    }
}
