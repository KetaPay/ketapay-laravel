<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use KetaPay\Laravel\Events\WebhookReceived;
use KetaPay\Laravel\Testing\InteractsWithKetaPayWebhooks;
use KetaPay\Laravel\Tests\TestCase;
use KetaPay\Models\Escrow;
use KetaPay\Webhook;
use PHPUnit\Framework\Attributes\TestDox;

final class WebhookTest extends TestCase
{
    use InteractsWithKetaPayWebhooks;

    /** @return array<string, mixed> */
    private function funded(): array
    {
        return $this->ketapayEvent('escrow.funded', ['object' => 'escrow', 'id' => 'e1', 'status' => 'active']);
    }

    #[TestDox('dispatches a verified event as WebhookReceived and as ketapay.<type>, then answers 200')]
    public function testDispatches(): void
    {
        $received = [];
        Event::listen(WebhookReceived::class, function (WebhookReceived $w) use (&$received): void {
            $received[] = 'class:' . $w->event->type;
        });
        Event::listen('ketapay.escrow.funded', function (WebhookReceived $w) use (&$received): void {
            self::assertInstanceOf(Escrow::class, $w->event->data->object);
            $received[] = 'named:' . $w->event->data->object->id;
        });
        Event::listen('ketapay.*', function (string $name, array $payload) use (&$received): void {
            $received[] = "wildcard:$name";
        });

        $this->postKetaPayWebhook($this->funded())->assertOk()->assertExactJson(['received' => true]);

        self::assertSame(['class:escrow.funded', 'named:e1', 'wildcard:ketapay.escrow.funded'], $received);
    }

    #[TestDox('refuses a bad signature, a missing header or an old timestamp with 400, dispatching nothing')]
    public function testRefuses(): void
    {
        Event::fake([WebhookReceived::class, 'ketapay.*']);
        $body = (string) json_encode($this->funded());

        $this->postKetaPayWebhook($body, 'whsec_wrong')->assertStatus(400)->assertJsonPath('error.code', 'signature_mismatch');
        $this->postKetaPayWebhook($body, timestamp: time() - 301)->assertStatus(400)->assertJsonPath('error.code', 'timestamp_outside_tolerance');
        $this->call('POST', '/ketapay/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)
            ->assertStatus(400)->assertJsonPath('error.code', 'missing_signature');
        // A body changed after signing.
        $header = Webhook::generateTestSignatureHeader($body, self::WEBHOOK_SECRET);
        $this->call('POST', '/ketapay/webhook', [], [], [], ['HTTP_KETAPAY_SIGNATURE' => $header, 'CONTENT_TYPE' => 'application/json'], str_replace('e1', 'e2', $body))
            ->assertStatus(400)->assertJsonPath('error.code', 'signature_mismatch');

        Event::assertNotDispatched(WebhookReceived::class);
        Event::assertNotDispatched('ketapay.escrow.funded');
    }

    #[TestDox('accepts the old secret while rotating (comma-separated secrets) and honours a custom tolerance')]
    public function testRotationAndTolerance(): void
    {
        Event::fake();
        config()->set('ketapay.webhook.secret', 'whsec_new, whsec_old');
        $this->postKetaPayWebhook($this->funded(), 'whsec_old')->assertOk();
        config()->set('ketapay.webhook.tolerance', 600);
        $this->postKetaPayWebhook($this->funded(), 'whsec_new', time() - 400)->assertOk();
        Event::assertDispatchedTimes(WebhookReceived::class, 2);
    }

    #[TestDox('answers 500 and logs when no webhook secret is configured')]
    public function testMissingSecret(): void
    {
        Event::fake([WebhookReceived::class]);
        $log = Log::spy();
        $body = (string) json_encode($this->funded());
        config()->set('ketapay.webhook.secret', '');
        $this->postKetaPayWebhook($body, 'whsec_anything')->assertStatus(500)->assertJsonPath('error.code', 'webhook_secret_missing');
        Event::assertNotDispatched(WebhookReceived::class);
        $log->shouldHaveReceived('error')->withArgs(static fn(string $message): bool => str_contains($message, 'KETAPAY_WEBHOOK_SECRET'))->once();
    }

    #[TestDox('acknowledges a repeated delivery without dispatching it again')]
    public function testDedupe(): void
    {
        Event::fake();
        $event = $this->funded();
        $this->postKetaPayWebhook($event)->assertOk();
        $this->postKetaPayWebhook($event)->assertOk()->assertJsonPath('duplicate', true);
        $this->postKetaPayWebhook($this->funded())->assertOk();
        Event::assertDispatchedTimes(WebhookReceived::class, 2);

        config()->set('ketapay.webhook.dedupe_seconds', 0);
        $this->postKetaPayWebhook($event)->assertOk();
        Event::assertDispatchedTimes(WebhookReceived::class, 3);
    }

    #[TestDox('fails with 500 when a listener throws, and dispatches the retry again')]
    public function testListenerFailure(): void
    {
        $calls = 0;
        Event::listen(WebhookReceived::class, function () use (&$calls): void {
            if (++$calls === 1) {
                throw new \RuntimeException('database down');
            }
        });
        $event = $this->funded();
        $this->withoutExceptionHandling();
        try {
            $this->postKetaPayWebhook($event);
            self::fail('the listener exception should fail the request');
        } catch (\RuntimeException $e) {
            self::assertSame('database down', $e->getMessage());
        }
        $this->postKetaPayWebhook($event)->assertOk()->assertExactJson(['received' => true]);
        self::assertSame(2, $calls);
    }

    #[TestDox('needs no CSRF token or session, and can be queued (the event serializes)')]
    public function testStatelessAndQueueable(): void
    {
        $route = Route::getRoutes()->getByName('ketapay.webhook');
        self::assertNotNull($route);
        self::assertNotContains('web', $route->gatherMiddleware());

        $webhook = null;
        Event::listen(WebhookReceived::class, function (WebhookReceived $w) use (&$webhook): void {
            $webhook = $w;
        });
        $this->postKetaPayWebhook($this->funded())->assertOk();
        self::assertInstanceOf(WebhookReceived::class, $webhook);
        $copy = unserialize(serialize($webhook));
        self::assertInstanceOf(WebhookReceived::class, $copy);
        self::assertInstanceOf(Escrow::class, $copy->event->data->object);
        self::assertSame('e1', $copy->event->data->object->id);
    }

    #[TestDox('the controller refuses a request that skipped the signature middleware')]
    public function testControllerNeedsMiddleware(): void
    {
        Route::post('/unprotected', \KetaPay\Laravel\Http\Controllers\WebhookController::class);
        $this->postJson('/unprotected', $this->funded())->assertStatus(400)->assertJsonPath('error.code', 'not_verified');
    }

    #[TestDox('testing helper explains a missing secret or a disabled route')]
    public function testHelperErrors(): void
    {
        config()->set('ketapay.webhook.secret', null);
        try {
            $this->postKetaPayWebhook($this->funded());
            self::fail('expected a LogicException');
        } catch (\LogicException $e) {
            self::assertStringContainsString('ketapay.webhook.secret', $e->getMessage());
        }
        config()->set('ketapay.webhook.path', null);
        $this->expectExceptionMessage('turned off');
        $this->postKetaPayWebhook($this->funded(), 'whsec_x');
    }
}
