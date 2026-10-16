<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

/** A page of `GET /opt-outs`. Ask for `$page + 1` while `hasMore`. */
final readonly class OptOutPage
{
    /**
     * @param  list<OptOut>  $items
     */
    public function __construct(
        public array $items,
        public int $currentPage,
        public int $lastPage,
        public int $perPage,
        public int $total,
    ) {}

    public function hasMore(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * @param  array<array-key, mixed>  $body  the whole response
     */
    public static function fromArray(array $body): self
    {
        $items = [];

        foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $row) {
            if (is_array($row)) {
                $items[] = OptOut::fromArray($row);
            }
        }

        $meta = is_array($body['meta'] ?? null) ? $body['meta'] : [];

        return new self(
            $items,
            Cast::int($meta['current_page'] ?? null, 1),
            Cast::int($meta['last_page'] ?? null, 1),
            Cast::int($meta['per_page'] ?? null, count($items)),
            Cast::int($meta['total'] ?? null, count($items)),
        );
    }
}
