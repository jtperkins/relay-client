<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** The Idempotency-Key was already used for a different request (code idempotency_key_reused). A programming error: one key per logical attempt, with the body stored. */
final class IdempotencyKeyReused extends RelayRefused {}
