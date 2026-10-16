<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Events;

/**
 * A signed callback of an event this client version does not know. It is acknowledged with 204 (a
 * new relay event never breaks an old client); listen for it to log or to adopt the event early.
 */
final readonly class UnknownEventReceived
{
    /**
     * @param  array<array-key, mixed>  $payload  the decoded body
     */
    public function __construct(
        public string $event,
        public array $payload,
    ) {}
}
