<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** The from address is not one of the project's senders for the channel (code sender_not_in_project). A configuration error. */
final class SenderNotInProject extends RelayRefused {}
