<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/**
 * A `message.inbound` callback. `optOut` is `stop`, `start`, `help` or null (the word the message
 * was); `optOutState` is the pair's state at routing, null when no word ever touched it. Callbacks
 * are at least once: dedupe on `deliveryId`.
 */
final readonly class InboundMessage
{
    public function __construct(
        public string $deliveryId,
        public string $id,
        public ?string $participantId,
        public string $channel,
        public string $from,
        public string $to,
        public string $body,
        public CarbonImmutable $receivedAt,
        public ?string $optOut,
        public ?OptOutState $optOutState,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  the callback's body
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data['delivery_id'] ?? null),
            Cast::string($data['id'] ?? null),
            Cast::nullableString($data['participant_id'] ?? null),
            Cast::string($data['channel'] ?? null),
            Cast::string($data['from'] ?? null),
            Cast::string($data['to'] ?? null),
            Cast::string($data['body'] ?? null),
            Cast::date($data['received_at'] ?? null),
            Cast::nullableString($data['opt_out'] ?? null) === null ? null : strtolower(Cast::string($data['opt_out'])),
            is_array($data['opt_out_state'] ?? null) ? OptOutState::fromArray($data['opt_out_state']) : null,
        );
    }
}
