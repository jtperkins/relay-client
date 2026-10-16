<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Testing;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

/**
 * Loads the relay's contract fixtures, bundled in `resources/fixtures/`: bytes the relay itself
 * produced (`artisan relay:contract-fixtures --api`), so a client test runs against the real wire
 * format. Callback fixtures are signed with the secret `test_secret` at timestamp `1760000000`; API
 * fixtures are `{status, headers, body}` vectors.
 *
 *   RelayFixtures::callback('opt-out-stopped')   // ['event', 'headers', 'body', 'secret', 'timestamp']
 *   RelayFixtures::payload('inbound-yes')        // the callback's decoded body
 *   RelayFixtures::api('refusal-no-sender')      // ['status', 'headers', 'body']
 *   Http::fake(['*' => RelayFixtures::response('send-accepted-202')])
 */
final class RelayFixtures
{
    public static function path(string $file = ''): string
    {
        return dirname(__DIR__, 2).'/resources/fixtures'.($file === '' ? '' : '/'.$file);
    }

    /**
     * @return array{event: string, headers: array<string, string>, body: string, secret: string, timestamp: int}
     */
    public static function callback(string $name): array
    {
        /** @var array{event: string, headers: array<string, string>, body: string, secret: string, timestamp: int} */
        return self::read($name.'.json');
    }

    /**
     * The decoded body of a callback fixture.
     *
     * @return array<string, mixed>
     */
    public static function payload(string $name): array
    {
        /** @var array<string, mixed> */
        return json_decode(self::callback($name)['body'], true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array{status: int, headers: array<string, string>, body: string}
     */
    public static function api(string $name): array
    {
        /** @var array{status: int, headers: array<string, string>, body: string} */
        return self::read('api/'.$name.'.json');
    }

    /** An API fixture as a faked HTTP response, for `Http::fake()`. */
    public static function response(string $name): PromiseInterface
    {
        $vector = self::api($name);

        return Http::response($vector['body'], $vector['status'], $vector['headers']);
    }

    /**
     * Every callback fixture's name.
     *
     * @return list<string>
     */
    public static function callbacks(): array
    {
        return self::names(self::path());
    }

    /**
     * Every API fixture's name.
     *
     * @return list<string>
     */
    public static function apis(): array
    {
        return self::names(self::path('api'));
    }

    /**
     * @return array<string, mixed>
     */
    private static function read(string $file): array
    {
        $path = self::path($file);

        if (! is_file($path)) {
            throw new InvalidArgumentException("No relay fixture {$file}.");
        }

        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return list<string>
     */
    private static function names(string $dir): array
    {
        $names = [];

        foreach (glob($dir.'/*.json') ?: [] as $file) {
            $names[] = basename($file, '.json');
        }

        sort($names);

        return $names;
    }
}
