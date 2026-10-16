<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** The token lacks the ability the call needs (403), for example messages:send. A configuration error. */
final class Forbidden extends RelayRefused {}
