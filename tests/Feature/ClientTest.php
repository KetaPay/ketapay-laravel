<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Log;
use KetaPay\Http\Transport;
use KetaPay\KetaPay;
use KetaPay\Laravel\Facades\KetaPay as KetaPayFacade;
use KetaPay\Laravel\Tests\Support\FakeTransport;
use KetaPay\Laravel\Tests\TestCase;
use KetaPay\Resources\Escrows;
use PHPUnit\Framework\Attributes\TestDox;

final class ClientTest extends TestCase
{
    #[TestDox('binds one client, built from the config, under its class and "ketapay"')]
    public function testSingleton(): void
    {
        $client = $this->laravel()->make(KetaPay::class);
        self::assertInstanceOf(KetaPay::class, $client);
        self::assertFalse($client->livemode);
        self::assertSame($client, $this->laravel()->make(KetaPay::class));
        self::assertSame($client, $this->laravel()->make('ketapay'));
    }

    #[TestDox('sends requests with the configured key, base URL, retries and timeout')]
    public function testConfigReachesRequests(): void
    {
        config()->set('ketapay.base_url', 'https://staging.example.com/v1');
        config()->set('ketapay.timeout_ms', 5000);
        config()->set('ketapay.max_retries', 0);
        $fake = new FakeTransport(['object' => 'escrow', 'id' => 'e1'], 503);
        $this->laravel()->instance(Transport::class, $fake);

        try {
            $this->laravel()->make(KetaPay::class)->escrows->retrieve('e1');
            self::fail('expected a 503 error');
        } catch (\KetaPay\Errors\APIError) {
        }
        self::assertCount(1, $fake->requests, 'max_retries 0: no retry');
        self::assertSame('https://staging.example.com/v1/escrows/e1', $fake->requests[0]->url);
        self::assertSame('Bearer sk_test_laravel', $fake->requests[0]->headers['Authorization']);
        self::assertSame(5000, $fake->requests[0]->timeoutMs);
    }

    #[TestDox('reaches resources through the facade, as methods')]
    public function testFacade(): void
    {
        $fake = new FakeTransport(['object' => 'escrow', 'id' => 'e1']);
        $this->laravel()->instance(Transport::class, $fake);
        self::assertInstanceOf(Escrows::class, KetaPayFacade::escrows());
        self::assertSame('e1', KetaPayFacade::escrows()->retrieve('e1')->id);
        self::assertFalse(KetaPayFacade::livemode());
        self::assertTrue(is_a('KetaPay', KetaPayFacade::class, true), 'the KetaPay alias points at the facade');
    }

    #[TestDox('logs to the configured channel, never the key')]
    public function testLogChannel(): void
    {
        config()->set('logging.channels.ketapay', ['driver' => 'single', 'path' => storage_path('logs/ketapay-test.log')]);
        config()->set('ketapay.log_channel', 'ketapay');
        config()->set('ketapay.log_level', 'debug');
        $this->laravel()->instance(Transport::class, new FakeTransport(['object' => 'escrow', 'id' => 'e1']));
        @unlink(storage_path('logs/ketapay-test.log'));

        $this->laravel()->make(KetaPay::class)->escrows->retrieve('e1');

        $log = (string) file_get_contents(storage_path('logs/ketapay-test.log'));
        self::assertStringContainsString('[ketapay] response', $log);
        self::assertStringContainsString('req_fake', $log);
        self::assertStringNotContainsString('sk_test_laravel', $log);
    }

    #[TestDox('says which .env variable is missing when there is no secret key')]
    public function testMissingSecret(): void
    {
        config()->set('ketapay.secret', null);
        $this->expectExceptionMessage('KETAPAY_SECRET_KEY');
        $this->laravel()->make(KetaPay::class);
    }

    #[TestDox('refuses a publishable key, through the SDK')]
    public function testPublishableKey(): void
    {
        config()->set('ketapay.secret', 'pk_test_x');
        $this->expectExceptionMessage('publishable');
        $this->laravel()->make(KetaPay::class);
    }

    #[TestDox('publishes the config file')]
    public function testPublish(): void
    {
        $target = config_path('ketapay.php');
        @unlink($target);
        self::assertSame(0, \Illuminate\Support\Facades\Artisan::call('vendor:publish', ['--tag' => 'ketapay-config']));
        self::assertFileExists($target);
        self::assertStringContainsString('KETAPAY_WEBHOOK_SECRET', (string) file_get_contents($target));
        unlink($target);
    }

    #[TestDox('falls back to the default log channel and defaults when config is partial')]
    public function testDefaults(): void
    {
        Log::spy();
        config()->set('ketapay.log_level', 'loud');
        config()->set('ketapay.timeout_ms', null);
        $config = \KetaPay\Laravel\Config::client($this->laravel());
        self::assertSame('warn', $config['logLevel']);
        self::assertSame(30_000, $config['timeoutMs']);
        self::assertArrayNotHasKey('baseUrl', $config);
    }
}
