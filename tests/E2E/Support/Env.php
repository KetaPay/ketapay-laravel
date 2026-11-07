<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\E2E\Support;

/**
 * E2E settings, the same ones every KetaPay SDK suite uses. CI reads them from Infisical (project
 * ketapay-sdks, environment staging).
 *
 *   KETAPAY_E2E_BASE_URL    API base URL, e.g. https://<staging>/v1   (required)
 *   KETAPAY_E2E_SECRET_KEY  sk_test_ key of the e2e business          (required)
 *   KETAPAY_E2E_RUN_ID      stamped on everything the suite creates   (optional)
 */
final class Env
{
    /** In the sandbox every one-time code is this, and no message is sent. */
    public const SANDBOX_CODE = '123456';

    private static ?string $runId = null;

    public static function baseUrl(): ?string
    {
        return self::get('KETAPAY_E2E_BASE_URL');
    }

    public static function key(): ?string
    {
        return self::get('KETAPAY_E2E_SECRET_KEY');
    }

    /** The suite runs only when the required settings exist. */
    public static function configured(): bool
    {
        return self::baseUrl() !== null && self::key() !== null;
    }

    /** Identifies this run, so the data it leaves on the API can be found and cleaned up. */
    public static function runId(): string
    {
        return self::$runId ??= self::get('KETAPAY_E2E_RUN_ID') ?? 'e2e-laravel-' . base_convert((string) time(), 10, 36);
    }

    private static function get(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
