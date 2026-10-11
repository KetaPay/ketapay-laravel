<?php

declare(strict_types=1);

namespace KetaPay\Laravel\Events;

use KetaPay\Models\Event;

/**
 * A verified webhook from KetaPay. Dispatched twice: as this class, and under the name
 * `ketapay.<event type>` (for example `ketapay.escrow.funded`), so you can listen to one type,
 * a family (`ketapay.milestone.*`) or everything.
 *
 * KetaPay waits 10 seconds for a response: do slow work in a queued listener (ShouldQueue).
 */
final class WebhookReceived
{
    public function __construct(public readonly Event $event) {}
}
