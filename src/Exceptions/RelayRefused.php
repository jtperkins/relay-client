<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

use Throwable;

/**
 * The relay answered with a 4xx. Do not retry it unchanged: fix the request, the token or the
 * project, except `RateLimited`, which says when to try again. A 4xx the client does not know is
 * this class itself.
 */
class RelayRefused extends RelayException
{
    /**
     * @param  array<array-key, mixed>  $body  the decoded response body
     */
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ?string $refusalCode = null,
        public readonly array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
