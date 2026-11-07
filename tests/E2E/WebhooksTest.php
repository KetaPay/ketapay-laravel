<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Tests\E2E;

use Illuminate\Support\Facades\Event;
use KetaPay\Laravel\Events\WebhookReceived;
use KetaPay\Laravel\Facades\KetaPay;
use KetaPay\Laravel\Testing\InteractsWithKetaPayWebhooks;
use KetaPay\Laravel\Tests\E2E\Support\E2ETestCase;
use KetaPay\Models\Escrow;
use PHPUnit\Framework\Attributes\TestDox;

final class WebhooksTest extends E2ETestCase
{
    use InteractsWithKetaPayWebhooks;

    #[TestDox('[LE2E-004] the real escrow.funded event reaches listeners as an Escrow, and delivering it again is acknowledged without dispatching')]
    public function testFundedEvent(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow();
        $event = $this->realEvent('escrow.funded', $escrow->id);
        $received = [];
        Event::listen(WebhookReceived::class, function (WebhookReceived $w) use (&$received): void {
            $received[] = $w->event->id;
        });
        Event::listen('ketapay.escrow.funded', function (WebhookReceived $w) use (&$received, $escrow): void {
            self::assertInstanceOf(Escrow::class, $w->event->data->object);
            self::assertSame($escrow->id, $w->event->data->object->id);
            $received[] = 'escrow.funded';
        });

        $this->postKetaPayWebhook($event)->assertOk()->assertExactJson(['received' => true]);
        $this->postKetaPayWebhook($event)->assertOk()->assertJsonPath('duplicate', true);

        self::assertSame([$event['id'], 'escrow.funded'], $received);
    }

    #[TestDox('[LE2E-005] after the seller delivers and the buyer releases through the facade, the real milestone.released event is dispatched for that escrow')]
    public function testReleasedEvent(): void
    {
        ['escrow' => $escrow, 'buyer' => $buyer, 'seller' => $seller] = self::fundedEscrow();
        $m = $escrow->milestones[0];
        KetaPay::escrows()->milestones->deliver($escrow->id, $m->id, ['note' => 'Designs ready'], ['customerToken' => self::tokenFor($seller)]);
        $released = KetaPay::escrows()->milestones->release($escrow->id, $m->id, [], ['customerToken' => self::tokenFor($buyer)]);
        self::assertSame('released', $released->milestones[0]->status);

        $event = $this->realEvent('milestone.released', $escrow->id);
        $matched = 0;
        Event::listen('ketapay.milestone.released', function (WebhookReceived $w) use (&$matched, $escrow): void {
            self::assertTrue(self::belongsTo($w->event, $escrow->id));
            ++$matched;
        });

        $this->postKetaPayWebhook($event)->assertOk();
        self::assertSame(1, $matched);
    }

    #[TestDox('[LE2E-006] a listener that fails on a real event answers 500 so KetaPay retries, and the retry is dispatched, not treated as a duplicate')]
    public function testListenerFailureRetries(): void
    {
        ['escrow' => $escrow] = self::fundedEscrow();
        $event = $this->realEvent('escrow.funded', $escrow->id);
        $calls = 0;
        Event::listen('ketapay.escrow.funded', function () use (&$calls): void {
            if (++$calls === 1) {
                throw new \RuntimeException('database down');
            }
        });

        $this->postKetaPayWebhook($event)->assertStatus(500);
        $this->postKetaPayWebhook($event)->assertOk()->assertExactJson(['received' => true]);
        self::assertSame(2, $calls);
    }
}
