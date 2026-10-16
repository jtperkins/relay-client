<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Testing;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Jtperkins\RelayClient\Support\CallbackSignature;

/**
 * For a feature test that posts the relay's callbacks to the app: signs a payload the way the relay
 * does, or replays a bundled fixture with its own secret and time.
 *
 *   use SignsRelayCallbacks;
 *
 *   $this->postRelayCallback(['id' => …, 'from' => …], 'message.inbound');
 *   $this->postRelayFixture('opt-out-stopped');
 *
 * `relayCallbackUri` is where the app called `Route::relayCallbacks()` (`/api/relay/callback` in a
 * Laravel `routes/api.php`); set the property on the test to change it.
 *
 * @mixin \Illuminate\Foundation\Testing\TestCase
 */
trait SignsRelayCallbacks
{
    protected string $relayCallbackUri = '/api/relay/callback';

    protected string $relayTestSecret = 'test_secret';

    /**
     * Sign `$payload` as `$event` (`delivery_id` and `event` are added when missing), set the secret
     * and post it.
     *
     * @param  array<string, mixed>  $payload
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function postRelayCallback(array $payload, string $event, ?string $secret = null, ?int $timestamp = null, ?string $uri = null): TestResponse
    {
        $secret ??= $this->relayTestSecret;
        $timestamp ??= CarbonImmutable::now()->getTimestamp();
        $body = (string) json_encode(['delivery_id' => (string) Str::ulid(), 'event' => $event] + $payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        config(['relay.callback_secret' => $secret]);

        return $this->postRelayRaw($body, ['Relay-Event' => $event, CallbackSignature::HEADER => CallbackSignature::header($body, $timestamp, $secret)], $uri);
    }

    /**
     * Replay a bundled callback fixture: its secret is configured, the clock is set to its signing
     * time (`Carbon::setTestNow`, left in place for the rest of the test) and its exact bytes and
     * headers are posted. Pass `$mutate` to change the decoded body and re-sign it.
     *
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $mutate
     * @return TestResponse<\Illuminate\Http\Response>
     */
    protected function postRelayFixture(string $name, ?callable $mutate = null, ?string $uri = null): TestResponse
    {
        $fixture = RelayFixtures::callback($name);

        config(['relay.callback_secret' => $fixture['secret']]);
        CarbonImmutable::setTestNow(CarbonImmutable::createFromTimestampUTC($fixture['timestamp']));
        \Illuminate\Support\Carbon::setTestNow(\Illuminate\Support\Carbon::createFromTimestampUTC($fixture['timestamp']));

        if ($mutate === null) {
            return $this->postRelayRaw($fixture['body'], $fixture['headers'], $uri);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($fixture['body'], true, flags: JSON_THROW_ON_ERROR);
        $body = (string) json_encode($mutate($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $headers = [CallbackSignature::HEADER => CallbackSignature::header($body, $fixture['timestamp'], $fixture['secret'])] + $fixture['headers'];

        return $this->postRelayRaw($body, $headers, $uri);
    }

    /**
     * @param  array<string, string>  $headers
     * @return TestResponse<\Illuminate\Http\Response>
     */
    private function postRelayRaw(string $body, array $headers, ?string $uri): TestResponse
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->call('POST', $uri ?? $this->relayCallbackUri, [], [], [], $server, $body);
    }
}
