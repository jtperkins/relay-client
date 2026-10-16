<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Exceptions;

/** The project is paused (403, code project_inactive). Sends are refused until an admin sets it Active. */
final class ProjectPaused extends RelayRefused {}
