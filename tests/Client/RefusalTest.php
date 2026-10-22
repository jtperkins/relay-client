<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use Jtperkins\RelayClient\Exceptions\Forbidden;
use Jtperkins\RelayClient\Exceptions\IdempotencyKeyReused;
use Jtperkins\RelayClient\Exceptions\InvalidRequest;
use Jtperkins\RelayClient\Exceptions\NoSender;
use Jtperkins\RelayClient\Exceptions\NotFound;
use Jtperkins\RelayClient\Exceptions\ProjectPaused;
use Jtperkins\RelayClient\Exceptions\RateLimited;
use Jtperkins\RelayClient\Exceptions\RecipientOptedOut;
use Jtperkins\RelayClient\Exceptions\RelayRefused;
use Jtperkins\RelayClient\Exceptions\RelayUnavailable;
use Jtperkins\RelayClient\Exceptions\SenderNotInProject;
use Jtperkins\RelayClient\Exceptions\Unauthenticated;
use Jtperkins\RelayClient\Facades\Relay;
use Jtperkins\RelayClient\Testing\RelayFixtures;

/*
 * Every API refusal the relay's fixtures hold maps to exactly one exception (the README's table).
 * The mapping is by `code` and status, never by message text.
 */

it('maps each refusal fixture to its exception', function (string $fixture, string $exception): void {
    Http::fake(['*' => RelayFixtures::response($fixture)]);

    expect(fn () => Relay::sendSms('+17345550142', 'Hi', 'attempt-1'))->toThrow($exception);
})->with([
    'refusal-recipient-opted-out' => ['refusal-recipient-opted-out', RecipientOptedOut::class],
    'refusal-invalid-recipient' => ['refusal-invalid-recipient', InvalidRequest::class],
    'refusal-idempotency-reused' => ['refusal-idempotency-reused', IdempotencyKeyReused::class],
    'refusal-sender-not-in-project' => ['refusal-sender-not-in-project', SenderNotInProject::class],
    'refusal-no-sender' => ['refusal-no-sender', NoSender::class],
    'paused-403' => ['paused-403', ProjectPaused::class],
    'unauthenticated-401' => ['unauthenticated-401', Unauthenticated::class],
    'rate-limited-429' => ['rate-limited-429', RateLimited::class],
]);

it('carries the status, the code and the body on a refusal', function (): void {
    Http::fake(['*' => RelayFixtures::response('refusal-recipient-opted-out')]);

    try {
        Relay::sendSms('+17345550142', 'Hi', 'attempt-1');
        $this->fail('Expected a refusal.');
    } catch (RelayRefused $refusal) {
        expect($refusal)->toBeInstanceOf(RecipientOptedOut::class)
            ->and($refusal->status)->toBe(422)
            ->and($refusal->refusalCode)->toBe('recipient_opted_out')
            ->and($refusal->body['errors'])->toHaveKey('to');
    }
});

it('names the failing field of an invalid request', function (): void {
    Http::fake(['*' => RelayFixtures::response('refusal-invalid-recipient')]);

    try {
        Relay::sendSms('nope', 'Hi', 'attempt-1');
        $this->fail('Expected a refusal.');
    } catch (InvalidRequest $invalid) {
        expect($invalid->field)->toBe('to')
            ->and($invalid->detail)->toBe('Enter a US phone number, for example (734) 555-0102.')
            ->and($invalid->refusalCode)->toBe('invalid_request');
    }
});

it('reads Retry-After on a 429', function (): void {
    Http::fake(['*' => RelayFixtures::response('rate-limited-429')]);

    try {
        Relay::sendSms('+17345550142', 'Hi', 'attempt-1');
        $this->fail('Expected a refusal.');
    } catch (RateLimited $limited) {
        expect($limited->retryAfter)->toBe(30)->and($limited->status)->toBe(429);
    }
});

it('maps by code, not by message: an unknown code on a 422 is an invalid request', function (): void {
    Http::fake(['*' => Http::response(['message' => 'The recipient has opted out of messages from this number.', 'errors' => ['to' => ['x']], 'code' => 'something_new'], 422)]);

    expect(fn () => Relay::sendSms('+17345550142', 'Hi', 'attempt-1'))->toThrow(InvalidRequest::class);
});

it('tells a missing ability from a paused project, both 403', function (): void {
    Http::fake(['*' => Http::response(['message' => 'This action is unauthorized.'], 403)]);

    expect(fn () => Relay::message('01k7a3z0q8m4v9x2b6c1d5e7f3'))->toThrow(Forbidden::class);
});

it('maps a 404 to NotFound, an unknown 4xx to RelayRefused, and a 5xx to RelayUnavailable', function (int $status, string $exception): void {
    Http::fake(['*' => Http::response(['message' => 'x'], $status)]);

    expect(fn () => Relay::message('01k7a3z0q8m4v9x2b6c1d5e7f3'))->toThrow($exception);
})->with([
    [404, NotFound::class],
    [409, RelayRefused::class],
    [500, RelayUnavailable::class],
    [502, RelayUnavailable::class],
]);

it('maps every API fixture to a documented outcome', function (): void {
    $names = RelayFixtures::apis();

    expect($names)->toHaveCount(14);

    foreach ($names as $name) {
        $status = RelayFixtures::api($name)['status'];
        expect($status)->toBeIn([200, 202, 401, 403, 422, 429], $name);
    }
});
