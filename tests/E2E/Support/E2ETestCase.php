<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\E2E\Support;

use KetaPay\Laravel\Facades\KetaPay;
use KetaPay\Laravel\Tests\TestCase;
use KetaPay\Models\Escrow;
use KetaPay\Models\Event;
use PHPUnit\Framework\AssertionFailedError;

/**
 * Base for E2E tests: the bridge in a Testbench app, configured only through config/ketapay.php, and
 * the real SDK against the KetaPay API with sandbox keys. Skipped when Env isn't configured.
 *
 * The API can't reach a webhook route inside a test process, so webhooks are the one part not
 * delivered for real: a test reads the real event from GET /v1/events, signs it and posts it to the
 * app's route, the way the WooCommerce suite does.
 *
 * @phpstan-import-type CreateCheckoutSessionRequest from \KetaPay\Types\Params
 */
abstract class E2ETestCase extends TestCase
{
    private static int $counter = 0;

    protected function setUp(): void
    {
        if (!Env::configured()) {
            self::markTestSkipped('E2E not configured: set KETAPAY_E2E_BASE_URL and KETAPAY_E2E_SECRET_KEY');
        }
        parent::setUp();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('ketapay.secret', Env::key());
        $app['config']->set('ketapay.base_url', Env::baseUrl());
        $app['config']->set('ketapay.max_retries', 1);
    }

    /**
     * A valid two-milestone deal between fresh people, tagged with the run id.
     *
     * @return array{params: CreateCheckoutSessionRequest, buyer: string, seller: string}
     */
    protected static function deal(): array
    {
        $n = ++self::$counter;
        $run = Env::runId();
        $buyer = "buyer+$run-$n@e2e.ketapay.test";
        $seller = "seller+$run-$n@e2e.ketapay.test";

        return [
            'params' => [
                'amount' => 1_500_000,
                'title' => 'E2E Laravel website build',
                'description' => 'Three-page site, built by the Laravel e2e suite to test the bridge.',
                'milestones' => [
                    ['title' => 'Design', 'amount' => 500_000],
                    ['title' => 'Build', 'amount' => 1_000_000],
                ],
                'buyer' => ['email' => $buyer, 'name' => 'E2E Buyer'],
                'seller' => ['email' => $seller, 'name' => 'E2E Seller'],
                'release' => ['method' => 'buyer_confirms'],
                'external_reference' => "$run-$n",
                'metadata' => ['e2e_run' => $run],
            ],
            'buyer' => $buyer,
            'seller' => $seller,
        ];
    }

    /**
     * Creates a checkout session through the facade and pays it with the test helper.
     *
     * @return array{escrow: Escrow, buyer: string, seller: string}
     */
    protected static function fundedEscrow(): array
    {
        $deal = self::deal();
        $session = KetaPay::checkoutSessions()->create($deal['params']);
        $paid = KetaPay::testHelpers()->checkoutSessions->pay($session->id);

        return ['escrow' => KetaPay::escrows()->retrieve((string) $paid->escrow_id), 'buyer' => $deal['buyer'], 'seller' => $deal['seller']];
    }

    /** Signs a customer in with the sandbox code and returns their customer token. */
    protected static function tokenFor(string $email): string
    {
        $session = KetaPay::customerSessions()->create(['email' => $email]);

        return (string) KetaPay::customerSessions()->verify($session->id, Env::SANDBOX_CODE)->token;
    }

    /**
     * The real event of a type whose object is, or belongs to, the escrow, as the API sends it.
     *
     * @param \KetaPay\Enums\EventType::* $type
     *
     * @return array<string, mixed>
     */
    protected function realEvent(string $type, string $escrowId): array
    {
        $found = null;
        $this->eventually(function () use ($type, $escrowId, &$found): void {
            foreach (KetaPay::events()->list(['type' => $type, 'limit' => 100])->data as $event) {
                if (self::belongsTo($event, $escrowId)) {
                    $found = $event->toArray();

                    return;
                }
            }
            self::fail("No $type event for escrow $escrowId");
        });
        self::assertIsArray($found);

        return $found;
    }

    protected static function belongsTo(Event $event, string $escrowId): bool
    {
        $object = $event->data->object;

        return $object->id === $escrowId || $object['escrow_id'] === $escrowId;
    }

    /** Retries an assertion, for effects that land a moment after the API call returns. */
    protected function eventually(callable $check, float $timeoutSeconds = 20): void
    {
        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            try {
                $check();

                return;
            } catch (AssertionFailedError $e) {
                if (microtime(true) >= $deadline) {
                    throw $e;
                }
                usleep(500_000);
            }
        }
    }
}
