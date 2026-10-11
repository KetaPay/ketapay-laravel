<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Http\Middleware;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use KetaPay\Errors\WebhookSignatureError;
use KetaPay\Laravel\Config;
use KetaPay\Webhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the KetaPay-Signature header against the raw body and puts the event on the request
 * (`$request->attributes->get(VerifyWebhookSignature::EVENT)`). Refuses anything that doesn't verify
 * with 400. Usable on your own route if you register it yourself.
 */
final class VerifyWebhookSignature
{
    public const EVENT = 'ketapay.event';

    public function __construct(private readonly Container $app) {}

    public function handle(Request $request, \Closure $next): Response
    {
        $secrets = Config::webhookSecrets($this->app);
        if ($secrets === []) {
            $log = $this->app->make('log');
            if ($log instanceof LoggerInterface) {
                $log->error('[ketapay] webhook refused: set KETAPAY_WEBHOOK_SECRET in your .env');
            }

            return self::error(500, 'webhook_secret_missing', 'The webhook signing secret is not configured');
        }

        try {
            $event = Webhook::verify((string) $request->getContent(), $request->header('KetaPay-Signature'), $secrets, Config::webhookTolerance($this->app));
        } catch (WebhookSignatureError $e) {
            return self::error(400, $e->code, $e->getMessage());
        }
        $request->attributes->set(self::EVENT, $event);

        $response = $next($request);

        return $response instanceof Response ? $response : new JsonResponse($response);
    }

    private static function error(int $status, string $code, string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
