<?php

declare(strict_types=1);

use Jtperkins\RelayClient\Tests\CallbackTestCase;
use Jtperkins\RelayClient\Tests\TestCase;

uses(TestCase::class)->in('Client', 'Package');
uses(CallbackTestCase::class)->in('Callbacks');
