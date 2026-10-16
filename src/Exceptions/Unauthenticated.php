<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** The token is missing, malformed, unknown or revoked, or its project was deleted (401). A configuration error. */
final class Unauthenticated extends RelayRefused {}
