<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\E2E;

use KetaPay\Errors\AuthenticationError;
use KetaPay\KetaPay as Client;
use KetaPay\Laravel\Facades\KetaPay;
use KetaPay\Laravel\Tests\E2E\Support\E2ETestCase;
use KetaPay\Laravel\Tests\E2E\Support\Env;
use PHPUnit\Framework\Attributes\TestDox;

final class ClientTest extends E2ETestCase
{
    #[TestDox('[LE2E-001] the client built from config/ketapay.php creates a real checkout session, and the injected client reads it back')]
    public function testConfiguredClient(): void
    {
        $session = KetaPay::checkoutSessions()->create(self::deal()['params']);
        self::assertStringStartsWith('http', (string) $session->url);
        self::assertFalse(KetaPay::livemode());

        $again = $this->laravel()->make(Client::class)->checkoutSessions->retrieve($session->id);
        self::assertSame($session->id, $again->id);
        self::assertSame(Env::runId(), $again->metadata['e2e_run']);
    }

    #[TestDox('[LE2E-002] a wrong key in the config surfaces the API\'s authentication error through the facade')]
    public function testWrongKey(): void
    {
        config()->set('ketapay.secret', 'sk_test_' . str_repeat('0', 32));
        $this->expectException(AuthenticationError::class);
        KetaPay::escrows()->list(['limit' => 1]);
    }

    #[TestDox('[LE2E-003] the SDK logs real requests to the configured Laravel log channel, without the key')]
    public function testLogChannel(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ketapay-e2e-log');
        self::assertIsString($file);
        config()->set('logging.channels.ketapay_e2e', ['driver' => 'single', 'path' => $file, 'level' => 'debug']);
        config()->set('ketapay.log_channel', 'ketapay_e2e');
        config()->set('ketapay.log_level', 'debug');

        try {
            KetaPay::escrows()->list(['limit' => 1]);
            $log = (string) file_get_contents($file);
        } finally {
            @unlink($file);
        }

        self::assertStringContainsString('/escrows', $log);
        self::assertStringNotContainsString((string) Env::key(), $log);
    }
}
