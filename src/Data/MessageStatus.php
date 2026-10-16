<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/**
 * One message as `GET /messages/{id}` (and a send's answer) return it. `to` is masked by the relay
 * (an SMS's last four digits) and the body is never echoed. `history` is every status report, oldest first.
 */
final readonly class MessageStatus
{
    /**
     * @param  list<HistoryEntry>  $history
     */
    public function __construct(
        public string $id,
        public string $channel,
        public string $status,
        public string $to,
        public ?string $from,
        public ?string $reference,
        public ?string $providerMessageId,
        public ?string $errorCode,
        public ?CarbonImmutable $createdAt,
        public ?CarbonImmutable $sentAt,
        public ?CarbonImmutable $statusUpdatedAt,
        public array $history = [],
    ) {}

    /**
     * @param  array<array-key, mixed>  $data  the response's `data` object
     */
    public static function fromArray(array $data): self
    {
        $history = [];

        foreach (is_array($data['history'] ?? null) ? $data['history'] : [] as $entry) {
            if (is_array($entry)) {
                $history[] = HistoryEntry::fromArray($entry);
            }
        }

        return new self(
            Cast::string($data['id'] ?? null),
            Cast::string($data['channel'] ?? null),
            Cast::string($data['status'] ?? null),
            Cast::string($data['to'] ?? null),
            Cast::nullableString($data['from'] ?? null),
            Cast::nullableString($data['reference'] ?? null),
            Cast::nullableString($data['provider_message_id'] ?? null),
            Cast::nullableString($data['error_code'] ?? null),
            Cast::nullableDate($data['created_at'] ?? null),
            Cast::nullableDate($data['sent_at'] ?? null),
            Cast::nullableDate($data['status_updated_at'] ?? null),
            $history,
        );
    }
}
