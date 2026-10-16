<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Tests;

use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;

/** An app that mounts the callback route the way a Laravel `routes/api.php` does: under the `api` prefix. */
abstract class CallbackTestCase extends TestCase
{
    /**
     * @param  Router  $router
     */
    protected function defineRoutes($router): void
    {
        Route::prefix('api')->group(function (): void {
            Route::relayCallbacks();
        });
    }
}
