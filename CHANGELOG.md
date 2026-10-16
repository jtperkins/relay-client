# Changelog

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
