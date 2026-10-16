<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Events;

use Jtperkins\RelayClient\Data\StatusUpdate;

/**
 * A signed message.status callback was received. Dispatched synchronously, so a
 * listener that throws answers the relay a 500 and the relay retries: callbacks are at least once,
 * so a listener must be idempotent (dedupe on the `deliveryId`).
 */
final readonly class MessageStatusReceived
{
    public function __construct(public StatusUpdate $update) {}
}
