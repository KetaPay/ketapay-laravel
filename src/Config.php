<?php

declare(strict_types=1);

namespace KetaPay\Laravel;

use Illuminate\Contracts\Container\Container;
use KetaPay\Http\Transport;
use Psr\Log\LoggerInterface;

/**
 * Reads config/ketapay.php, with clear errors for what is missing. Internal.
 *
 * @internal
 */
final class Config
{
    /**
     * The KetaPay client's config. A `KetaPay\Http\Transport` bound in the container is used to send
     * requests, for example a fake in your tests.
     *
     * @return array{apiKey: string, baseUrl?: string, maxRetries: int, timeoutMs: int, logLevel: 'off'|'error'|'warn'|'info'|'debug', logger: LoggerInterface, transport?: Transport}
     */
    public static function client(Container $app): array
    {
        $secret = self::get($app, 'ketapay.secret');
        if (!is_string($secret) || $secret === '') {
            throw new \RuntimeException('KetaPay: set KETAPAY_SECRET_KEY in your .env (sk_test_... or sk_live_...).');
        }
        $level = self::get($app, 'ketapay.log_level');
        $level = in_array($level, ['off', 'error', 'warn', 'info', 'debug'], true) ? $level : 'warn';
        $channel = self::get($app, 'ketapay.log_channel');
        $log = $app->make('log');
        $logger = is_string($channel) && $channel !== '' && method_exists($log, 'channel') ? $log->channel($channel) : $log;
        if (!$logger instanceof LoggerInterface) {
            throw new \RuntimeException('KetaPay: the log channel is not a PSR-3 logger.');
        }

        $config = [
            'apiKey' => $secret,
            'maxRetries' => self::int($app, 'ketapay.max_retries', 2),
            'timeoutMs' => self::int($app, 'ketapay.timeout_ms', 30_000),
            'logLevel' => $level,
            'logger' => $logger,
        ];
        if ($app->bound(Transport::class)) {
            $transport = $app->make(Transport::class);
            if ($transport instanceof Transport) {
                $config['transport'] = $transport;
            }
        }
        $baseUrl = self::get($app, 'ketapay.base_url');
        if (is_string($baseUrl) && $baseUrl !== '') {
            $config['baseUrl'] = $baseUrl;
        }

        return $config;
    }

    /**
     * The webhook signing secrets: one, or two while rotating (comma separated in .env).
     *
     * @return list<string>
     */
    public static function webhookSecrets(Container $app): array
    {
        $value = self::get($app, 'ketapay.webhook.secret');
        $secrets = is_array($value) ? $value : explode(',', is_string($value) ? $value : '');

        return array_values(array_filter(array_map(static fn(mixed $s): string => is_string($s) ? trim($s) : '', $secrets), static fn(string $s): bool => $s !== ''));
    }

    public static function webhookPath(Container $app): ?string
    {
        $path = self::get($app, 'ketapay.webhook.path');

        return is_string($path) && trim($path, '/') !== '' ? '/' . trim($path, '/') : null;
    }

    /** @return list<string> */
    public static function webhookMiddleware(Container $app): array
    {
        $middleware = self::get($app, 'ketapay.webhook.middleware');

        return is_array($middleware) ? array_values(array_filter($middleware, 'is_string')) : [];
    }

    public static function webhookTolerance(Container $app): int
    {
        return self::int($app, 'ketapay.webhook.tolerance', 300);
    }

    public static function dedupeSeconds(Container $app): int
    {
        return self::int($app, 'ketapay.webhook.dedupe_seconds', 259_200);
    }

    private static function get(Container $app, string $key): mixed
    {
        $config = $app->make('config');

        return $config instanceof \Illuminate\Contracts\Config\Repository ? $config->get($key) : null;
    }

    private static function int(Container $app, string $key, int $default): int
    {
        $value = self::get($app, $key);

        return is_numeric($value) ? (int) $value : $default;
    }
}
