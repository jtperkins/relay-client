# Changelog

## Unreleased

- `sendEmail` takes an optional trailing `?string $html = null`: the HTML part, sent beside the
  plain-text `$body` (the relay's M225). The payload carries `html` only when it is given.
- The bundled fixtures gain the email cases: the `status-email-delivered` callback and the
  `send-email-accepted-202` API vector (the relay's M226), each reporting one email message of its
  own, so there are now 12 callback fixtures and 14 API vectors.

## 0.1.0 — 2026-10-16

First release.

- `RelayClient` (facade `Relay`): `sendSms`, `sendEmail`, `message`, `numbers`, `optOuts`. Typed
  answers (`SentMessage`, `MessageStatus`, `Number`, `OptOutPage`), no HTTP retry, `Idempotency-Key`
  and `X-Request-Id` support.
- One exception per refusal `code`: `RecipientOptedOut`, `InvalidRequest`, `IdempotencyKeyReused`,
  `SenderNotInProject`, `NoSender`, `ProjectPaused`, `Unauthenticated`, `Forbidden`, `NotFound`,
  `RateLimited`, `RelayUnavailable`.
- `Route::relayCallbacks()` (signature check, then throttle) and the typed events
  `MessageStatusReceived`, `InboundMessageReceived`, `OptOutChanged`, `UnknownEventReceived`.
- Testing helpers: `SignsRelayCallbacks`, `RelayFixtures`, and the relay's contract fixtures bundled
  in `resources/fixtures/`.
