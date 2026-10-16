<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

use Throwable;

/**
 * A 429. Wait `retryAfter` seconds (the `Retry-After` header, 1 when it is missing) and try again:
 * release the queued job for that long instead of throwing it into its tries.
 */
final class RateLimited extends RelayRefused
{
    /**
     * @param  array<array-key, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly int $retryAfter = 1,
        array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 429, null, $body, $previous);
    }
}
