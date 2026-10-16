<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Jtperkins\RelayClient\Testing\RelayFixtures;

it('registers no route of its own where the app never calls Route::relayCallbacks()', function (): void {
    $relayRoutes = collect(Route::getRoutes()->getRoutes())->filter(fn (Illuminate\Routing\Route $route): bool => str_contains($route->uri(), 'relay'));

    expect($relayRoutes)->toHaveCount(0)
        ->and(Route::hasMacro('relayCallbacks'))->toBeTrue();
});

it('merges its config and publishes it', function (): void {
    expect(config('relay.timeout'))->toBe(10)
        ->and(config('relay.connect_timeout'))->toBe(5)
        ->and(config('relay.signature_tolerance'))->toBe(300)
        ->and(config('relay.callback.throttle'))->toBe('1000,1');
});

it('never export-ignores what runs: /src, /config and /resources (the bundled fixtures)', function (): void {
    $attributes = (string) file_get_contents(dirname(__DIR__, 2).'/.gitattributes');
    $ignored = [];

    foreach (explode("\n", $attributes) as $line) {
        if (str_contains($line, 'export-ignore')) {
            $ignored[] = trim(explode(' ', $line)[0]);
        }
    }

    expect($ignored)->toContain('/tests')
        ->and($ignored)->not->toContain('/src')
        ->and($ignored)->not->toContain('/config')
        ->and($ignored)->not->toContain('/resources')
        ->and(is_file(RelayFixtures::path('inbound-stop.json')))->toBeTrue()
        ->and(is_file(RelayFixtures::path('api/paused-403.json')))->toBeTrue();
});

it('names no product: nothing in the package names the app it was built for', function (): void {
    $root = dirname(__DIR__, 2);
    $word = 'care'.'chat';
    $found = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        $path = (string) $file->getPathname();

        if (str_contains($path, '/vendor/') || str_contains($path, '/.git/') || str_contains($path, '/.phpunit.cache/') || str_contains($path, '/.phpstan-cache/')) {
            continue;
        }

        if (stripos((string) file_get_contents($path), $word) !== false) {
            $found[] = substr($path, strlen($root) + 1);
        }
    }

    expect($found)->toBe([]);
});
