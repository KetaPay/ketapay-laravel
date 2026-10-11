<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use KetaPay\Laravel\Events\WebhookReceived;
use KetaPay\Laravel\Testing\InteractsWithKetaPayWebhooks;
use KetaPay\Laravel\Tests\TestCase;
use PHPUnit\Framework\Attributes\TestDox;

/** Route options are read at boot, so each case sets them before the app starts. */
final class RouteConfigTest extends TestCase
{
    use InteractsWithKetaPayWebhooks;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ketapay.webhook.path', match ($this->name()) {
            'testCustomPath' => '/hooks/ketapay/',
            'testDisabled' => null,
            default => 'ketapay/webhook',
        });
        if ($this->name() === 'testExtraMiddleware') {
            $app['config']->set('ketapay.webhook.middleware', ['throttle:1,1']);
        }
    }

    #[TestDox('serves the webhook at a custom path')]
    public function testCustomPath(): void
    {
        Event::fake();
        self::assertSame('hooks/ketapay', Route::getRoutes()->getByName('ketapay.webhook')?->uri());
        $this->postKetaPayWebhook($this->ketapayEvent('escrow.funded', ['object' => 'escrow', 'id' => 'e1']))->assertOk();
        Event::assertDispatched(WebhookReceived::class);
    }

    #[TestDox('registers no route when the path is null')]
    public function testDisabled(): void
    {
        self::assertNull(Route::getRoutes()->getByName('ketapay.webhook'));
        $this->postJson('/ketapay/webhook', [])->assertNotFound();
    }

    #[TestDox('adds the configured middleware to the route')]
    public function testExtraMiddleware(): void
    {
        Event::fake();
        $this->postKetaPayWebhook($this->ketapayEvent('escrow.funded', ['object' => 'escrow', 'id' => 'e1']))->assertOk();
        $this->postKetaPayWebhook($this->ketapayEvent('escrow.funded', ['object' => 'escrow', 'id' => 'e2']))->assertStatus(429);
    }
}
