<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

use RuntimeException;

/** Anything the relay client throws: catch this to catch every refusal and every outage. */
class RelayException extends RuntimeException {}
