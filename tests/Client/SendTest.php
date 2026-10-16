<?php

declare(strict_types=1);

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Jtperkins\RelayClient\Exceptions\RelayException;
use Jtperkins\RelayClient\Exceptions\RelayUnavailable;
use Jtperkins\RelayClient\Facades\Relay;
use Jtperkins\RelayClient\RelayClient;
use Jtperkins\RelayClient\Testing\RelayFixtures;

beforeEach(function (): void {
    Http::preventStrayRequests();
});

it('is configured only with a base URL and a token', function (): void {
    expect(resolve(RelayClient::class)->configured())->toBeTrue();

    config(['relay.token' => '']);
    expect(Relay::configured())->toBeFalse();

    config(['relay.token' => 'x', 'relay.base_url' => null]);
    expect(Relay::configured())->toBeFalse();
});

it('refuses to call a relay that is not configured, before any request', function (): void {
    config(['relay.base_url' => null]);

    expect(fn () => Relay::sendSms('(734) 555-0142', 'Hi', 'k1'))->toThrow(RelayException::class, 'not configured');
    Http::assertNothingSent();
});

it('sends an SMS with the token, the idempotency key and the request id, and reads the answer', function (): void {
    Http::fake(['relay.example.test/*' => RelayFixtures::response('send-accepted-202')]);

    $sent = Relay::sendSms('(734) 555-0142', 'Your visit is tomorrow.', 'attempt-1', 'reminder-4812', '+17345550100', 'trace-42');

    expect($sent->id)->toBe('01k7a3z0q8m4v9x2b6c1d5e7f3')
        ->and($sent->status)->toBe('sent')
        ->and($sent->replayed)->toBeFalse()
        ->and($sent->message->reference)->toBe('reminder-4812')
        ->and($sent->message->providerMessageId)->toBe('SM00000000000000000000000000000001')
        ->and($sent->message->history)->toHaveCount(1)
        ->and($sent->message->createdAt?->toIso8601String())->toBe('2025-10-09T08:53:10+00:00');

    Http::assertSent(function (Request $request): bool {
        return $request->url() === 'https://relay.example.test/api/v1/messages'
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer msg_live_test_token')
            && $request->hasHeader('Idempotency-Key', 'attempt-1')
            && $request->hasHeader('X-Request-Id', 'trace-42')
            && $request->hasHeader('Accept', 'application/json')
            && $request->data() === [
                'channel' => 'sms',
                'to' => '(734) 555-0142',
                'body' => 'Your visit is tomorrow.',
                'reference' => 'reminder-4812',
                'from' => '+17345550100',
            ];
    });
});

it('sends an email, and leaves out an absent reference, sender and a request id the relay would refuse', function (): void {
    Http::fake(['relay.example.test/*' => RelayFixtures::response('send-accepted-202')]);

    Relay::sendEmail('ada@example.test', 'Your visit', 'See you at 9.', 'attempt-2', null, null, 'not a valid id!');

    Http::assertSent(fn (Request $request): bool => $request->data() === ['channel' => 'email', 'to' => 'ada@example.test', 'subject' => 'Your visit', 'body' => 'See you at 9.']
        && ! $request->hasHeader('X-Request-Id'));
});

it('marks a 200 as a replay', function (): void {
    Http::fake(['relay.example.test/*' => RelayFixtures::response('send-replayed-200')]);

    expect(Relay::sendSms('+17345550142', 'Hi', 'attempt-1')->replayed)->toBeTrue();
});

it('retries nothing: a 503 is one request and a RelayUnavailable', function (): void {
    Http::fake(['relay.example.test/*' => Http::response('', 503)]);

    expect(fn () => Relay::sendSms('+17345550142', 'Hi', 'attempt-1'))->toThrow(RelayUnavailable::class);
    Http::assertSentCount(1);
});

it('turns a timeout or no connection into a RelayUnavailable', function (): void {
    Http::fake(['relay.example.test/*' => fn () => throw new ConnectionException('cURL error 28: timed out')]);

    expect(fn () => Relay::sendSms('+17345550142', 'Hi', 'attempt-1'))->toThrow(RelayUnavailable::class, 'could not be reached');
});

it('reads a message, the numbers and a page of opt-outs', function (): void {
    Http::fake([
        'relay.example.test/api/v1/messages/*' => RelayFixtures::response('message-show'),
        'relay.example.test/api/v1/numbers' => RelayFixtures::response('numbers-index'),
        'relay.example.test/api/v1/opt-outs*' => RelayFixtures::response('opt-outs-index'),
    ]);

    $message = Relay::message('01k7a3z0q8m4v9x2b6c1d5e7f3');
    $numbers = Relay::numbers();
    $page = Relay::optOuts('+17345550142', '+17345550100', true, new DateTimeImmutable('2026-10-10T10:00:00+00:00'), 2);

    expect($message->status)->toBe('sent')
        ->and($message->history[0]->source)->toBe('send')
        ->and($numbers)->toHaveCount(1)
        ->and($numbers[0]->phone)->toBe('+17345550100')
        ->and($numbers[0]->isDefault)->toBeTrue()
        ->and($page->items)->toHaveCount(2)
        ->and($page->items[0]->optedOut)->toBeTrue()
        ->and($page->items[0]->source)->toBe('stop_keyword')
        ->and($page->items[1]->optedOut)->toBeFalse()
        ->and($page->items[1]->version)->toBe(2)
        ->and($page->total)->toBe(2)
        ->and($page->hasMore())->toBeFalse();

    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/opt-outs?')
        && str_contains($request->url(), 'opted_out=true')
        && str_contains($request->url(), 'page=2')
        && str_contains($request->url(), 'to=%2B17345550142'));
});
