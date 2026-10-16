<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Event;
use Jtperkins\RelayClient\Data\InboundMessage;
use Jtperkins\RelayClient\Data\OptOutChange;
use Jtperkins\RelayClient\Data\StatusUpdate;
use Jtperkins\RelayClient\Events\InboundMessageReceived;
use Jtperkins\RelayClient\Events\MessageStatusReceived;
use Jtperkins\RelayClient\Events\OptOutChanged;
use Jtperkins\RelayClient\Events\UnknownEventReceived;

/**
 * Receives the relay's signed callbacks (VerifyRelaySignature runs first) and fires the matching
 * event synchronously. A listener that throws is a 500, so the relay retries: delivery is at least
 * once, and a listener must be idempotent. A new event this version does not know is a 204
 * (`UnknownEventReceived`), so an old client never breaks on a new relay.
 *
 * Registered only by `Route::relayCallbacks()`.
 */
final class RelayCallbackController
{
    public function __invoke(Request $request): JsonResponse|Response
    {
        $data = json_decode($request->getContent(), true);

        if (! is_array($data) || ! is_string($data['event'] ?? null)) {
            return response()->json(['error' => 'malformed'], 400);
        }

        match ($data['event']) {
            'message.status' => Event::dispatch(new MessageStatusReceived(StatusUpdate::fromArray($data))),
            'message.inbound' => Event::dispatch(new InboundMessageReceived(InboundMessage::fromArray($data))),
            'opt_out.changed' => Event::dispatch(new OptOutChanged(OptOutChange::fromArray($data))),
            default => Event::dispatch(new UnknownEventReceived($data['event'], $data)),
        };

        return response()->noContent();
    }
}
