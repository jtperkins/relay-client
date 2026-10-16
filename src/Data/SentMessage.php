<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

/**
 * What `POST /messages` answered: the message and whether it was a replay of an earlier request with
 * the same Idempotency-Key (`replayed`, a 200) rather than a new message (a 202).
 */
final readonly class SentMessage
{
    public function __construct(
        public string $id,
        public string $status,
        public bool $replayed,
        public MessageStatus $message,
    ) {}
}
