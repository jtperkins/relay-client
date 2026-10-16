<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/** One (our number, their number) pair as `GET /opt-outs` lists it. `source` is null while the pair is opted in. */
final readonly class OptOut
{
    public function __construct(
        public string $id,
        public string $number,
        public string $recipient,
        public bool $optedOut,
        public int $version,
        public ?string $source,
        public CarbonImmutable $changedAt,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data['id'] ?? null),
            Cast::string($data['number'] ?? null),
            Cast::string($data['recipient'] ?? null),
            Cast::bool($data['opted_out'] ?? false),
            Cast::int($data['version'] ?? null),
            Cast::nullableString($data['source'] ?? null),
            Cast::date($data['changed_at'] ?? null),
        );
    }
}
