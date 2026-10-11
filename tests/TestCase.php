<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests;

use KetaPay\Laravel\KetaPayServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected const WEBHOOK_SECRET = 'whsec_test_laravel';

    /** The booted app (Testbench types $this->app as nullable). */
    protected function laravel(): \Illuminate\Foundation\Application
    {
        return $this->app ?? throw new \LogicException('The app is not booted');
    }

    protected function getPackageProviders($app): array
    {
        return [KetaPayServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['KetaPay' => \KetaPay\Laravel\Facades\KetaPay::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('ketapay.secret', 'sk_test_laravel');
        $app['config']->set('ketapay.webhook.secret', self::WEBHOOK_SECRET);
        $app['config']->set('cache.default', 'array');
    }
}
