<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * The app's KetaPay client. Resources are reached as methods: `KetaPay::escrows()->retrieve($id)`
 * is `$ketapay->escrows->retrieve($id)` on the injected client.
 *
 * @method static \KetaPay\Resources\CheckoutSessions checkoutSessions()
 * @method static \KetaPay\Resources\CustomerSessions customerSessions()
 * @method static \KetaPay\Resources\Escrows escrows()
 * @method static \KetaPay\Resources\Disputes disputes()
 * @method static \KetaPay\Resources\Chargebacks chargebacks()
 * @method static \KetaPay\Resources\PayoutAccounts payoutAccounts()
 * @method static \KetaPay\Resources\Banks banks()
 * @method static \KetaPay\Resources\Files files()
 * @method static \KetaPay\Resources\WebhookEndpoints webhookEndpoints()
 * @method static \KetaPay\Resources\Events events()
 * @method static \KetaPay\Resources\Webhooks webhooks()
 * @method static \KetaPay\Resources\TestHelpers testHelpers()
 * @method static bool livemode()
 *
 * @see \KetaPay\KetaPay
 */
final class KetaPay extends Facade
{
    /**
     * @param string       $method
     * @param array<mixed> $args
     */
    public static function __callStatic($method, $args): mixed
    {
        $client = static::getFacadeRoot();
        if ($args === [] && is_object($client) && property_exists($client, $method)) {
            return $client->{$method};
        }

        return parent::__callStatic($method, $args);
    }

    protected static function getFacadeAccessor(): string
    {
        return \KetaPay\KetaPay::class;
    }
}
