<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/**
 * A 5xx, a timeout or no connection. Retryable: the queue's tries are the only retry, and the
 * Idempotency-Key makes a retried send a replay, not a second message.
 */
final class RelayUnavailable extends RelayException {}
