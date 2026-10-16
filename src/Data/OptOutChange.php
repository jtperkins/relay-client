<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/**
 * An `opt_out.changed` callback: a pair's state after a flip. Order by `version`, never by arrival or
 * `changedAt`: apply it only when `isNewerThan` the version you hold. `cause` is `keyword`, `carrier`
 * or `provider_error`; `source` is null when the pair opted in.
 */
final readonly class OptOutChange
{
    public function __construct(
        public string $deliveryId,
        public string $id,
        public string $number,
        public string $recipient,
        public bool $optedOut,
        public int $version,
        public ?string $source,
        public string $cause,
        public CarbonImmutable $changedAt,
    ) {}

    public function isNewerThan(int $version): bool
    {
        return $this->version > $version;
    }

    /**
     * @param  array<array-key, mixed>  $data  the callback's body
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data['delivery_id'] ?? null),
            Cast::string($data['id'] ?? null),
            Cast::string($data['number'] ?? null),
            Cast::string($data['recipient'] ?? null),
            Cast::bool($data['opted_out'] ?? false),
            Cast::int($data['version'] ?? null),
            Cast::nullableString($data['source'] ?? null),
            Cast::string($data['cause'] ?? null),
            Cast::date($data['changed_at'] ?? null),
        );
    }
}
