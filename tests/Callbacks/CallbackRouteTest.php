<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Jtperkins\RelayClient\Http\Controllers\RelayCallbackController;
use Jtperkins\RelayClient\Http\Middleware\VerifyRelaySignature;

it('serves exactly one route, POST api/relay/callback, where the app calls the macro under its api prefix', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(fn (Illuminate\Routing\Route $route): bool => str_contains($route->uri(), 'relay'));

    expect($routes)->toHaveCount(1);

    $route = $routes->first();
    expect($route->uri())->toBe('api/relay/callback')
        ->and($route->methods())->toBe(['POST'])
        ->and($route->getName())->toBe('relay.callback')
        ->and($route->getActionName())->toBe(RelayCallbackController::class)
        ->and($route->gatherMiddleware())->toBe([VerifyRelaySignature::class, 'throttle:1000,1']);
});

it('takes the throttle from config', function (): void {
    config(['relay.callback.throttle' => '5,1']);

    Route::relayCallbacks('other/hook', 'other.hook');
    Route::getRoutes()->refreshNameLookups();

    expect(Route::getRoutes()->getByName('other.hook')?->gatherMiddleware())->toBe([VerifyRelaySignature::class, 'throttle:5,1']);
});
