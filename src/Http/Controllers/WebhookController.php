<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Http\Controllers;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use KetaPay\Laravel\Config;
use KetaPay\Laravel\Events\WebhookReceived;
use KetaPay\Laravel\Http\Middleware\VerifyWebhookSignature;
use KetaPay\Models\Event;

/**
 * Dispatches a verified webhook as Laravel events and acknowledges it. An exception in a listener
 * fails the request (500), so KetaPay retries the delivery; the event id is only remembered once
 * every listener has run.
 */
final class WebhookController
{
    public function __invoke(Request $request, Dispatcher $events, Cache $cache, Container $app): JsonResponse
    {
        $event = $request->attributes->get(VerifyWebhookSignature::EVENT);
        if (!$event instanceof Event) {
            return new JsonResponse(['error' => ['code' => 'not_verified', 'message' => 'Use the VerifyWebhookSignature middleware on this route']], 400);
        }

        $ttl = Config::dedupeSeconds($app);
        $key = 'ketapay:webhook:' . (string) $event->id;
        if ($ttl > 0 && $cache->has($key)) {
            return new JsonResponse(['received' => true, 'duplicate' => true]);
        }

        $webhook = new WebhookReceived($event);
        $events->dispatch($webhook);
        $events->dispatch('ketapay.' . (string) $event->type, [$webhook]);

        if ($ttl > 0) {
            $cache->put($key, true, $ttl);
        }

        return new JsonResponse(['received' => true]);
    }
}
