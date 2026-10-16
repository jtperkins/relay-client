<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

/**
 * The pair's state when a `message.inbound` was routed: apply this, never the message's `opt_out`
 * word (a START routed after a newer STOP still says opted out, with the STOP's higher version).
 */
final readonly class OptOutState
{
    public function __construct(
        public bool $optedOut,
        public int $version,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(Cast::bool($data['opted_out'] ?? false), Cast::int($data['version'] ?? null));
    }
}
