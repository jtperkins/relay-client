<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Tests;

use Jtperkins\RelayClient\RelayClientServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/** A bare Laravel app with the package booted and a configured relay; no route of the package's. */
abstract class TestCase extends Orchestra
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RelayClientServiceProvider::class];
    }

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('relay.base_url', 'https://relay.example.test/api/v1');
        $app['config']->set('relay.token', 'msg_live_test_token');
        $app['config']->set('relay.callback_secret', 'test_secret');
    }
}
