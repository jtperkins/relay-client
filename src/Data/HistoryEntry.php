<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/** One status report in a message's history (`GET /messages/{id}`). `status` is null for a report that changes nothing, such as a SendGrid click. */
final readonly class HistoryEntry
{
    public function __construct(
        public ?string $status,
        public ?string $providerStatus,
        public ?string $errorCode,
        public CarbonImmutable $occurredAt,
        public string $source,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::nullableString($data['status'] ?? null),
            Cast::nullableString($data['provider_status'] ?? null),
            Cast::nullableString($data['error_code'] ?? null),
            Cast::date($data['occurred_at'] ?? null),
            Cast::string($data['source'] ?? null),
        );
    }
}
