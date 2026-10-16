<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Jtperkins\RelayClient\Events\InboundMessageReceived;
use Jtperkins\RelayClient\Events\MessageStatusReceived;
use Jtperkins\RelayClient\Events\OptOutChanged;
use Jtperkins\RelayClient\Events\UnknownEventReceived;
use Jtperkins\RelayClient\Support\CallbackSignature;
use Jtperkins\RelayClient\Testing\RelayFixtures;
use Jtperkins\RelayClient\Testing\SignsRelayCallbacks;

uses(SignsRelayCallbacks::class);

beforeEach(function (): void {
    Event::fake([MessageStatusReceived::class, InboundMessageReceived::class, OptOutChanged::class, UnknownEventReceived::class]);
});

it('accepts a body signed with the current secret', function (): void {
    $this->postRelayCallback(['id' => 'in-1', 'channel' => 'sms', 'from' => '+17345550142', 'to' => '+17345550100', 'body' => 'Hi'], 'message.inbound')->assertNoContent();

    Event::assertDispatched(InboundMessageReceived::class);
});

it('accepts a body signed with the previous secret during a rotation', function (): void {
    config(['relay.callback_secret' => 'new_secret', 'relay.callback_secret_previous' => 'old_secret']);
    $body = (string) json_encode(['delivery_id' => 'd1', 'event' => 'message.inbound', 'id' => 'in-1', 'from' => '+17345550142']);

    $this->call('POST', '/api/relay/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_RELAY_SIGNATURE' => CallbackSignature::header($body, time(), 'old_secret'),
    ], $body)->assertNoContent();
});

it('accepts a header carrying one v1 per secret when any matches', function (): void {
    $body = (string) json_encode(['delivery_id' => 'd1', 'event' => 'message.inbound', 'id' => 'in-1']);
    $now = time();
    $header = 't='.$now.',v1='.hash_hmac('sha256', $now.'.'.$body, 'someone_elses').',v1='.hash_hmac('sha256', $now.'.'.$body, 'test_secret');

    $this->call('POST', '/api/relay/callback', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_RELAY_SIGNATURE' => $header], $body)->assertNoContent();
});

it('refuses a tampered body with a bare 403', function (): void {
    $fixture = RelayFixtures::callback('inbound-stop');
    config(['relay.callback_secret' => $fixture['secret']]);
    Carbon::setTestNow(Carbon::createFromTimestampUTC($fixture['timestamp']));

    $this->call('POST', '/api/relay/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_RELAY_SIGNATURE' => $fixture['headers']['Relay-Signature'],
    ], str_replace('STOP', 'HELP', $fixture['body']))->assertForbidden();

    Event::assertNothingDispatched();
});

it('refuses a stale timestamp, and a future one', function (int $skew): void {
    $fixture = RelayFixtures::callback('inbound-stop');
    config(['relay.callback_secret' => $fixture['secret']]);
    Carbon::setTestNow(Carbon::createFromTimestampUTC($fixture['timestamp'] + $skew));

    $this->call('POST', '/api/relay/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_RELAY_SIGNATURE' => $fixture['headers']['Relay-Signature'],
    ], $fixture['body'])->assertForbidden();
})->with([301, -301]);

it('accepts a timestamp inside the tolerance', function (): void {
    $fixture = RelayFixtures::callback('inbound-stop');
    config(['relay.callback_secret' => $fixture['secret']]);
    Carbon::setTestNow(Carbon::createFromTimestampUTC($fixture['timestamp'] + 299));

    $this->call('POST', '/api/relay/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_RELAY_SIGNATURE' => $fixture['headers']['Relay-Signature'],
    ], $fixture['body'])->assertNoContent();
});

it('refuses when the app has no secret set, whatever is signed', function (): void {
    config(['relay.callback_secret' => '', 'relay.callback_secret_previous' => null]);
    $body = (string) json_encode(['event' => 'message.inbound']);

    $this->call('POST', '/api/relay/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_RELAY_SIGNATURE' => CallbackSignature::header($body, time(), ''),
    ], $body)->assertForbidden();
});

it('refuses a missing or malformed signature header', function (string $header): void {
    $this->call('POST', '/api/relay/callback', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_RELAY_SIGNATURE' => $header], '{"event":"message.inbound"}')->assertForbidden();
})->with(['', 'garbage', 't=abc,v1=ff', 't=1760000000', 'v1=ff']);
