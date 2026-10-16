<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** The recipient opted out of the sending number (code recipient_opted_out). Stop sending to them until a START or YES. */
final class RecipientOptedOut extends RelayRefused {}
