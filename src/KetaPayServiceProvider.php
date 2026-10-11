<?php

declare(strict_types=1);

namespace KetaPay\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use KetaPay\KetaPay;
use KetaPay\Laravel\Http\Controllers\WebhookController;
use KetaPay\Laravel\Http\Middleware\VerifyWebhookSignature;

/**
 * Registers one KetaPay client for the app (built on first use from config/ketapay.php) and the
 * webhook route. Discovered automatically by Laravel.
 */
final class KetaPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ketapay.php', 'ketapay');

        // The client is stateless, so one instance serves every request, including under Octane.
        $this->app->singleton(KetaPay::class, static fn(Application $app): KetaPay => new KetaPay(Config::client($app)));
        $this->app->alias(KetaPay::class, 'ketapay');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/ketapay.php' => $this->app->configPath('ketapay.php')], 'ketapay-config');
        }

        $path = Config::webhookPath($this->app);
        if ($path !== null && !($this->app instanceof \Illuminate\Foundation\Application && $this->app->routesAreCached())) {
            Route::post($path, WebhookController::class)
                ->middleware([VerifyWebhookSignature::class, ...Config::webhookMiddleware($this->app)])
                ->name('ketapay.webhook');
        }
    }
}
