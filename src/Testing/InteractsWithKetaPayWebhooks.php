<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Testing;

use Illuminate\Testing\TestResponse;
use KetaPay\Laravel\Config;
use KetaPay\Webhook;

/**
 * For your feature tests: build an event and post it, correctly signed, to your webhook route.
 *
 *     use InteractsWithKetaPayWebhooks;
 *
 *     $this->postKetaPayWebhook($this->ketapayEvent('escrow.funded', ['object' => 'escrow', 'id' => $id]))
 *         ->assertOk();
 */
trait InteractsWithKetaPayWebhooks
{
    /**
     * An event as KetaPay sends it.
     *
     * @param array<string, mixed> $object    the event's data.object
     * @param array<string, mixed> $overrides top-level fields to replace
     *
     * @return array<string, mixed>
     */
    protected function ketapayEvent(string $type, array $object, array $overrides = []): array
    {
        return [
            'id' => 'evt_test_' . bin2hex(random_bytes(8)),
            'object' => 'event',
            'type' => $type,
            'api_version' => 'v1',
            'livemode' => false,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'data' => ['object' => $object],
            ...$overrides,
        ];
    }

    /**
     * Posts an event to the webhook route, signed with your configured secret (or the one given).
     *
     * @param array<string, mixed>|string $event an array from ketapayEvent(), or a raw body
     *
     * @return TestResponse<\Symfony\Component\HttpFoundation\Response>
     */
    protected function postKetaPayWebhook(array|string $event, ?string $secret = null, ?int $timestamp = null): TestResponse
    {
        $body = is_string($event) ? $event : json_encode($event, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $app = $this->app ?? throw new \LogicException('The app is not booted');
        $secret ??= Config::webhookSecrets($app)[0] ?? throw new \LogicException('Set ketapay.webhook.secret, or pass a secret');
        $path = Config::webhookPath($app) ?? throw new \LogicException('The KetaPay webhook route is turned off (ketapay.webhook.path is null)');

        return $this->call('POST', $path, [], [], [], [
            'HTTP_KETAPAY_SIGNATURE' => Webhook::generateTestSignatureHeader($body, $secret, $timestamp),
            'CONTENT_TYPE' => 'application/json',
        ], $body);
    }
}
