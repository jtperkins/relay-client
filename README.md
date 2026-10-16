# relay-client

The Laravel client for the study messaging relay (the relay's contract is its
`docs/relay.md`). A relay integration becomes configuration and listeners: a typed API client, one
exception per refusal, a signed callback route, typed events and test helpers.

- PHP `^8.2`, Laravel 11, 12 or 13. Runtime dependencies are `illuminate/{contracts,http,routing,support}`
  only, so `Http::fake()` and `Http::preventStrayRequests()` keep working.
- No migrations, no tables. Nothing here knows which app uses it.

## Install

The package is private (GitHub, `jtperkins/relay-client`). Add the repository and require an exact
version:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/jtperkins/relay-client" }
  ],
  "require": { "jtperkins/relay-client": "0.1.0" }
}
```

Composer needs a GitHub token that can read the repository, in `COMPOSER_AUTH` (never in a file you
commit):

```bash
COMPOSER_AUTH='{"github-oauth":{"github.com":"'"$(gh auth token)"'"}}' composer update jtperkins/relay-client
```

CI needs the same token as a secret. Laravel discovers the service provider and the `Relay` facade.

## Configure

```dotenv
RELAY_BASE_URL=https://messaging.example.edu/api/v1   # the project's API Client URL
RELAY_TOKEN=msg_live_…                                # a project API token
RELAY_CALLBACK_SECRET=…                               # printed once by `artisan project:callback`
RELAY_CALLBACK_SECRET_PREVIOUS=                       # only during a secret rotation's overlap
RELAY_TIMEOUT=10
```

`php artisan vendor:publish --tag=relay-config` copies `config/relay.php`. `Relay::configured()` is true
when the base URL and the token are both set: use it to keep an old path while the relay is off.

## Send

```php
use Jtperkins\RelayClient\Facades\Relay;

$sent = Relay::sendSms(
    to: '(734) 555-0142',
    body: $renderedBody,
    idempotencyKey: $attempt->idempotency_key,   // one key per logical attempt
    reference: 'reminder-4812',                  // your id, echoed on callbacks
    requestId: $traceId,                         // optional X-Request-Id (1-64 of A-Za-z0-9-)
);

$sent->id;        // the relay's message id
$sent->replayed;  // true when the same key had already been used (a 200)
```

`sendEmail($to, $subject, $body, $idempotencyKey, …)`, `message($id)` (status and history),
`numbers()` and `optOuts(to:, from:, optedOut:, changedSince:, page:)` follow the same shape.

**There is no HTTP retry in the client.** Retries belong to your queue. Use **one key per logical
attempt** and **store the body you rendered**: a retried job then sends the same bytes under the same
key, and the relay answers the original (`replayed`) instead of sending twice. A body that changed
between tries under one key is `IdempotencyKeyReused`.

### Refusals

Each exception extends `RelayException`; a 4xx extends `RelayRefused` (`status`, `refusalCode`,
`body`). Mapping is by the response's `code`, never by message text.

| Response                      | Exception              | What a client should do                                                             |
| ----------------------------- | ---------------------- | ----------------------------------------------------------------------------------- |
| 422 `recipient_opted_out`     | `RecipientOptedOut`    | Stop sending to them; they must text START or YES. Do not fail the job              |
| 422 `invalid_request`         | `InvalidRequest`       | `field` and `detail` say what: a malformed number is a data problem, not an opt-out |
| 422 `idempotency_key_reused`  | `IdempotencyKeyReused` | A bug: one key, one body                                                            |
| 422 `sender_not_in_project`   | `SenderNotInProject`   | Configuration: fix `from`                                                           |
| 422 `no_sender`               | `NoSender`             | Configuration: the project needs a Participant Communication sender                 |
| 403 `project_inactive`        | `ProjectPaused`        | The project is paused: stop and probe now and then; an admin resumes it             |
| 401                           | `Unauthenticated`      | Configuration: the token is wrong, revoked, or its project deleted                  |
| 403 (other)                   | `Forbidden`            | Configuration: the token lacks `messages:send` or `messages:read`                   |
| 404                           | `NotFound`             | No such message for this project                                                    |
| 429                           | `RateLimited`          | `retryAfter` seconds: `release()` the job for that long                             |
| 5xx, a timeout, no connection | `RelayUnavailable`     | Retry from the queue                                                                |
| any other 4xx                 | `RelayRefused`         | Treat as a refusal; read `status` and `body`                                        |

An unknown `code` on a 422 is an `InvalidRequest`, so a newer relay never breaks an older client.

## Receive callbacks

In `routes/api.php` (Laravel's `api` prefix applies):

```php
Route::relayCallbacks();   // POST /api/relay/callback
```

The package registers **no route of its own**: the route exists only where you call the macro (its
URI is relative to the group you call it in; pass a URI and a name to change them). The
signature is checked first (`Relay-Signature`, HMAC-SHA256 of `{t}.{raw body}`, the current and the
previous secret, within `relay.signature_tolerance` seconds), then `throttle:1000,1` (config
`relay.callback.throttle`). A bad signature, or no secret set, is a bare 403.

The controller fires events **synchronously** and answers 204:

| Event                    | Payload                        | When                                                          |
| ------------------------ | ------------------------------ | ------------------------------------------------------------- |
| `MessageStatusReceived`  | `->update` (`StatusUpdate`)    | a message you sent through the API changed status             |
| `InboundMessageReceived` | `->message` (`InboundMessage`) | a text arrived for the project, with the pair's `optOutState` |
| `OptOutChanged`          | `->change` (`OptOutChange`)    | an opt-out pair flipped, whatever the project's status        |
| `UnknownEventReceived`   | `->event`, `->payload`         | an event this version does not know (still a 204)             |

Delivery is **at least once** and may arrive out of order, so every listener must be idempotent:
dedupe on `deliveryId`; apply a `StatusUpdate` only when its `rank` is higher than the one you hold;
apply an `OptOutChange` only when `isNewerThan($yourVersion)`; and for an inbound message apply the
`optOutState` (`optedOut`, `version`), never the `optOut` word. A listener that throws answers a 500
and the relay retries.

## Testing

```php
use Jtperkins\RelayClient\Testing\RelayFixtures;
use Jtperkins\RelayClient\Testing\SignsRelayCallbacks;

Http::fake(['*' => RelayFixtures::response('refusal-recipient-opted-out')]);   // the relay's real 422
RelayFixtures::api('paused-403');            // ['status', 'headers', 'body']
RelayFixtures::callback('opt-out-stopped');  // a signed callback: headers, raw body, secret, timestamp

// In a feature test:
use SignsRelayCallbacks;
$this->postRelayFixture('inbound-yes')->assertNoContent();
$this->postRelayCallback(['id' => …, 'from' => …], 'message.inbound');   // signed with the test secret
```

`postRelayFixture` configures the fixture's secret, sets the clock to its signing time and posts its
exact bytes. The route is `/api/relay/callback` unless you set `$relayCallbackUri` on the test.

The fixtures in `resources/fixtures/` are **the relay's own output** (`artisan relay:contract-fixtures
--api`), so your tests run against the real wire format. They ship in the dist archive (the
`.gitattributes` never export-ignores `/resources`).

## Contributing and release

```bash
composer verify          # Pint, PHPStan level 8 (Larastan), Pest
scripts/sync-fixtures.sh # regenerate the fixtures from a relay checkout (default ../messaging-app)
```

After a relay contract change, run `scripts/sync-fixtures.sh`, review `git status` (a fixture the
relay stopped writing is removed by hand, never by script) and release.

**Release** only with the owner's say-so: CI green on the matrix, then tag `vX.Y.Z` on the commit
whose CHANGELOG entry is already dated, and push the tag. A consumer pins the exact version.

CI covers PHP 8.2-8.4 x Laravel 11-13 (13 needs PHP 8.3), plus one `--prefer-lowest` cell. Laravel 11
is past security support, so its cells set `COMPOSER_NO_SECURITY_BLOCKING=1`.
