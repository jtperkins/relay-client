<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Jtperkins\RelayClient\Http\Controllers\RelayCallbackController;
use Jtperkins\RelayClient\Http\Middleware\VerifyRelaySignature;

final class RelayClientServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/relay.php', 'relay');

        $this->app->singleton(RelayClient::class);
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../config/relay.php' => config_path('relay.php')], 'relay-config');

        /*
         * The one way the callback route exists: the package never registers it on its own, so it
         * lands exactly where the app puts it. Called in `routes/api.php` (prefix `api`) it serves
         * `POST /api/relay/callback`. The signature is checked before the throttle.
         */
        Route::macro('relayCallbacks', function (string $uri = 'relay/callback', string $name = 'relay.callback'): \Illuminate\Routing\Route {
            $throttle = config('relay.callback.throttle');

            return Route::post($uri, RelayCallbackController::class)
                ->middleware([VerifyRelaySignature::class, 'throttle:'.(is_string($throttle) && $throttle !== '' ? $throttle : '1000,1')])
                ->name($name);
        });
    }
}
