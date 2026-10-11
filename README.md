# KetaPay for Laravel

Laravel integration for the [KetaPay PHP library](https://github.com/KetaPay/ketapay-php): one configured client in the container, a facade, and verified webhooks delivered as Laravel events. Laravel 12 and 13, PHP 8.2 or later.

On an older Laravel, use the [PHP library](https://github.com/KetaPay/ketapay-php#frameworks-and-hosting) directly: it works on PHP 8.1 and binding it in a service provider takes five lines.

> Beta. The API is in design partner testing and may still change.

## Install

```sh
composer require ketapay/ketapay-laravel:@beta
```

Laravel discovers the package. Add your keys to `.env`:

```dotenv
KETAPAY_SECRET_KEY=sk_test_...
KETAPAY_WEBHOOK_SECRET=whsec_...
```

To change anything else (base URL, retries, timeout, log channel, webhook path), publish the config: `php artisan vendor:publish --tag=ketapay-config`.

## Calling the API

Type-hint the client anywhere Laravel injects, or use the facade. Both are the same client from the [PHP library](https://github.com/KetaPay/ketapay-php#readme), so everything in its README applies:

```php
use KetaPay\KetaPay;

class CheckoutController
{
    public function store(Order $order, KetaPay $ketapay)
    {
        $session = $ketapay->checkoutSessions->create([
            'amount' => $order->total_kobo, // ₦450,000 is 45_000_000
            'title' => $order->listing->title,
            'description' => $order->listing->description,
            'buyer' => ['email' => $order->buyer->email],
            'seller' => ['email' => $order->listing->seller->email],
            'external_reference' => "order_{$order->id}",
        ]);

        return redirect()->away($session->url);
    }
}
```

```php
use KetaPay\Laravel\Facades\KetaPay;

KetaPay::escrows()->retrieve($escrowId);
KetaPay::escrows()->milestones->release($escrowId, $milestoneId, [], ['customerToken' => $token]);
```

With the facade, resources are methods: `KetaPay::escrows()` is `$ketapay->escrows`. One client serves the whole app, including under Octane: it keeps no per-request state.

## Webhooks

The package registers `POST /ketapay/webhook`. Add that URL as a webhook endpoint in KetaPay, with your app's domain in front. Every delivery is verified against `KETAPAY_WEBHOOK_SECRET` before anything else runs; anything that doesn't verify is refused with `400`. The route has no session or CSRF middleware, so there is nothing to exclude.

A verified event is dispatched as `KetaPay\Laravel\Events\WebhookReceived`, and again under the name `ketapay.<type>`. Listen to one type, a family, or everything:

```php
use Illuminate\Support\Facades\Event;
use KetaPay\Laravel\Events\WebhookReceived;

Event::listen('ketapay.escrow.funded', function (WebhookReceived $webhook) {
    $escrow = $webhook->event->data->object; // a KetaPay\Models\Escrow
    Order::where('reference', $escrow->external_reference)->update(['status' => 'paid']);
});

Event::listen('ketapay.milestone.*', MilestoneListener::class);
Event::listen(WebhookReceived::class, LogEveryWebhook::class);
```

**Respond quickly.** KetaPay waits 10 seconds for an answer and otherwise retries. Do slow work (emails, API calls) in a queued listener: implement `ShouldQueue`; the event serializes cleanly.

**Duplicates are handled.** KetaPay retries failed deliveries for 3 days and may deliver an event twice. The package remembers event ids in your cache for 3 days (`webhook.dedupe_seconds`) and acknowledges a repeat without dispatching it again. An id is only remembered after every listener has run: if a listener throws, the request fails with `500` and KetaPay's retry is dispatched again. Listeners that change money or state should still be safe to run twice, because two deliveries can race.

**Rotating the secret.** While both secrets sign deliveries (24 hours), list both: `KETAPAY_WEBHOOK_SECRET=whsec_new,whsec_old`.

**Your own route.** Set `webhook.path` to `null` and register it with the middleware and controller:

```php
use KetaPay\Laravel\Http\Controllers\WebhookController;
use KetaPay\Laravel\Http\Middleware\VerifyWebhookSignature;

Route::post('/hooks/payments', WebhookController::class)->middleware(VerifyWebhookSignature::class);
```

## Testing your app

Post signed webhooks in your feature tests:

```php
use KetaPay\Laravel\Testing\InteractsWithKetaPayWebhooks;

class PaymentsTest extends TestCase
{
    use InteractsWithKetaPayWebhooks;

    public function test_a_funded_escrow_marks_the_order_paid(): void
    {
        $order = Order::factory()->create(['reference' => 'order_1']);

        $this->postKetaPayWebhook($this->ketapayEvent('escrow.funded', [
            'object' => 'escrow',
            'id' => 'esc_1',
            'external_reference' => 'order_1',
        ]))->assertOk();

        $this->assertSame('paid', $order->fresh()->status);
    }
}
```

To fake API calls, bind a `KetaPay\Http\Transport` in the container; the client sends every request through it:

```php
$this->app->instance(\KetaPay\Http\Transport::class, new MyFakeTransport());
```

## Logging

The client logs retries (and with `KETAPAY_LOG_LEVEL=debug`, every request with its status, request id and timing) to your default log channel, or `KETAPAY_LOG_CHANNEL`. Keys, customer tokens and bodies are never logged.

## Development

See [CONTRIBUTING.md](CONTRIBUTING.md).
