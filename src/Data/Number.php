<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

/** One of the project's SMS numbers (`GET /numbers`), any of which is a valid SMS `from`. */
final readonly class Number
{
    public function __construct(
        public string $phone,
        public string $label,
        public bool $isDefault,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            Cast::string($data['phone'] ?? null),
            Cast::string($data['label'] ?? null),
            Cast::bool($data['is_default'] ?? false),
        );
    }
}
