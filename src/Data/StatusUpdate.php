<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/**
 * A `message.status` callback, for a message sent through the API. Apply it only when `rank` is
 * higher than the one you hold (callbacks are at least once and may arrive out of order).
 */
final readonly class StatusUpdate
{
    public function __construct(
        public string $deliveryId,
        public string $id,
        public ?string $reference,
        public string $status,
        public int $rank,
        public string $providerStatus,
        public ?string $errorCode,
        public ?string $providerMessageId,
        public CarbonImmutable $occurredAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  the callback's body
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data['delivery_id'] ?? null),
            Cast::string($data['id'] ?? null),
            Cast::nullableString($data['reference'] ?? null),
            Cast::string($data['status'] ?? null),
            Cast::int($data['rank'] ?? null),
            Cast::string($data['provider_status'] ?? null),
            Cast::nullableString($data['error_code'] ?? null),
            Cast::nullableString($data['provider_message_id'] ?? null),
            Cast::date($data['occurred_at'] ?? null),
        );
    }
}
