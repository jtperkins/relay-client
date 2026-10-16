<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

use Throwable;

/**
 * A 422 with code `invalid_request` (or one this client version does not know): a rule failed.
 * `field` is the first failing field (`to` for a malformed number), `detail` its message.
 */
final class InvalidRequest extends RelayRefused
{
    /**
     * @param  array<array-key, mixed>  $body
     */
    public function __construct(
        string $message,
        public readonly ?string $field = null,
        public readonly ?string $detail = null,
        array $body = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 422, 'invalid_request', $body, $previous);
    }
}
