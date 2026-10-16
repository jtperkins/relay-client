<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** No from address was given and the project has no default sender for the channel (code no_sender). A configuration error. */
final class NoSender extends RelayRefused {}
