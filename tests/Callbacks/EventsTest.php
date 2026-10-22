<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Jtperkins\RelayClient\Events\InboundMessageReceived;
use Jtperkins\RelayClient\Events\MessageStatusReceived;
use Jtperkins\RelayClient\Events\OptOutChanged;
use Jtperkins\RelayClient\Events\UnknownEventReceived;
use Jtperkins\RelayClient\Testing\RelayFixtures;
use Jtperkins\RelayClient\Testing\SignsRelayCallbacks;

uses(SignsRelayCallbacks::class);

/*
 * Every callback fixture the relay wrote verifies, answers 204 and fires the right event.
 */

it('verifies every callback fixture and fires its event', function (string $name): void {
    Event::fake();
    $event = RelayFixtures::callback($name)['event'];

    $this->postRelayFixture($name)->assertNoContent();

    match ($event) {
        'message.status' => Event::assertDispatched(MessageStatusReceived::class),
        'message.inbound' => Event::assertDispatched(InboundMessageReceived::class),
        'opt_out.changed' => Event::assertDispatched(OptOutChanged::class),
    };
    Event::assertDispatchedTimes(match ($event) {
        'message.status' => MessageStatusReceived::class,
        'message.inbound' => InboundMessageReceived::class,
        'opt_out.changed' => OptOutChanged::class,
    }, 1);
})->with(fn (): array => RelayFixtures::callbacks());

it('has the twelve callback fixtures of the contract', function (): void {
    expect(RelayFixtures::callbacks())->toBe([
        'inbound-help', 'inbound-plain', 'inbound-start', 'inbound-stop', 'inbound-yes',
        'opt-out-provider-error', 'opt-out-started', 'opt-out-stopped',
        'status-delivered', 'status-email-delivered', 'status-failed', 'status-sent',
    ]);
});

it('parses an opt_out.changed into a versioned change', function (): void {
    Event::fake();

    $this->postRelayFixture('opt-out-stopped')->assertNoContent();

    Event::assertDispatched(OptOutChanged::class, function (OptOutChanged $event): bool {
        $change = $event->change;

        return $change->optedOut === true
            && $change->version === 1
            && $change->source === 'stop_keyword'
            && $change->cause === 'keyword'
            && $change->number === '+17345550100'
            && $change->recipient === '+17345550142'
            && $change->isNewerThan(0)
            && ! $change->isNewerThan(1)
            && $change->deliveryId === '01k7a3z1b2c3d4e5f6g7h8j9k0';
    });
});

it('parses an inbound YES with the pair\'s state, apart from its word', function (): void {
    Event::fake();

    $this->postRelayFixture('inbound-yes')->assertNoContent();

    Event::assertDispatched(InboundMessageReceived::class, function (InboundMessageReceived $event): bool {
        $message = $event->message;

        return $message->optOut === 'start'
            && $message->body === 'YES'
            && $message->optOutState?->optedOut === false
            && $message->optOutState->version === 2
            && $message->participantId === null;
    });
});

it('parses a plain inbound with no state', function (): void {
    Event::fake();

    $this->postRelayFixture('inbound-plain')->assertNoContent();

    Event::assertDispatched(InboundMessageReceived::class, fn (InboundMessageReceived $event): bool => $event->message->optOut === null && $event->message->optOutState === null);
});

it('parses a status update with its rank and provider id', function (): void {
    Event::fake();

    $this->postRelayFixture('status-failed')->assertNoContent();

    Event::assertDispatched(MessageStatusReceived::class, fn (MessageStatusReceived $event): bool => $event->update->status === 'failed'
        && $event->update->rank === 2
        && $event->update->errorCode === '30008'
        && $event->update->providerMessageId === 'SM00000000000000000000000000000001'
        && $event->update->reference === 'reminder-4812');
});

it('acknowledges an event it does not know with 204 and fires UnknownEventReceived', function (): void {
    Event::fake();

    $this->postRelayCallback(['thing' => 'new'], 'brand.new')->assertNoContent();

    Event::assertDispatched(UnknownEventReceived::class, fn (UnknownEventReceived $event): bool => $event->event === 'brand.new' && $event->payload['thing'] === 'new');
});

it('answers a signed body that is not an event object with 400', function (): void {
    $body = '[1,2]';
    config(['relay.callback_secret' => 'test_secret']);

    $this->call('POST', '/api/relay/callback', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_RELAY_SIGNATURE' => Jtperkins\RelayClient\Support\CallbackSignature::header($body, time(), 'test_secret'),
    ], $body)->assertStatus(400);
});

it('lets a listener\'s exception answer a 500, so the relay retries', function (): void {
    Event::listen(InboundMessageReceived::class, function (): void {
        throw new RuntimeException('database is down');
    });

    $this->withoutExceptionHandling();
    expect(fn () => $this->postRelayFixture('inbound-plain'))->toThrow(RuntimeException::class);
});
