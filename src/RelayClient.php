<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient;

use DateTimeInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Jtperkins\RelayClient\Data\MessageStatus;
use Jtperkins\RelayClient\Data\Number;
use Jtperkins\RelayClient\Data\OptOutPage;
use Jtperkins\RelayClient\Data\SentMessage;
use Jtperkins\RelayClient\Exceptions\Forbidden;
use Jtperkins\RelayClient\Exceptions\IdempotencyKeyReused;
use Jtperkins\RelayClient\Exceptions\InvalidRequest;
use Jtperkins\RelayClient\Exceptions\NoSender;
use Jtperkins\RelayClient\Exceptions\NotFound;
use Jtperkins\RelayClient\Exceptions\ProjectPaused;
use Jtperkins\RelayClient\Exceptions\RateLimited;
use Jtperkins\RelayClient\Exceptions\RecipientOptedOut;
use Jtperkins\RelayClient\Exceptions\RelayException;
use Jtperkins\RelayClient\Exceptions\RelayRefused;
use Jtperkins\RelayClient\Exceptions\RelayUnavailable;
use Jtperkins\RelayClient\Exceptions\SenderNotInProject;
use Jtperkins\RelayClient\Exceptions\Unauthenticated;

/**
 * The relay's API (docs/relay.md § API reference): one method per call, typed answers, one exception
 * per refusal. Mapping is by the response's `code`, never by message text; an unknown code on a 422
 * is an `InvalidRequest`.
 *
 * There is **no HTTP retry** here: retries belong to the caller's queue, and the `Idempotency-Key`
 * makes a retried send a replay (`SentMessage::$replayed`), not a second message. Use one key per
 * logical attempt and store the body you rendered, so a retry sends the same bytes.
 *
 * Resolved from the container (singleton) or the `Relay` facade. It reads `Http::` and the config at
 * call time, so `Http::fake()` and a test's config changes apply.
 */
final class RelayClient
{
    /** The relay accepts an X-Request-Id of 1-64 of these. */
    private const REQUEST_ID_PATTERN = '/^[A-Za-z0-9-]{1,64}$/';

    /** On when the base URL and the token are both set. */
    public function configured(): bool
    {
        return $this->setting('base_url') !== '' && $this->setting('token') !== '';
    }

    /**
     * Queue one SMS. `$to` is a US number in any common format; `$from` one of `numbers()`, else
     * the project's default.
     *
     * @throws RelayException
     */
    public function sendSms(string $to, string $body, string $idempotencyKey, ?string $reference = null, ?string $from = null, ?string $requestId = null): SentMessage
    {
        return $this->send(['channel' => 'sms', 'to' => $to, 'body' => $body], $idempotencyKey, $reference, $from, $requestId);
    }

    /**
     * Queue one email; `$body` is the plain-text part and `$html`, when given, is sent beside it.
     *
     * @throws RelayException
     */
    public function sendEmail(string $to, string $subject, string $body, string $idempotencyKey, ?string $reference = null, ?string $from = null, ?string $requestId = null, ?string $html = null): SentMessage
    {
        $payload = ['channel' => 'email', 'to' => $to, 'subject' => $subject, 'body' => $body];

        if ($html !== null) {
            $payload['html'] = $html;
        }

        return $this->send($payload, $idempotencyKey, $reference, $from, $requestId);
    }

    /** @throws RelayException */
    public function message(string $id): MessageStatus
    {
        $body = $this->check($this->call(fn (PendingRequest $request): Response => $request->get('/messages/'.rawurlencode($id))));

        return MessageStatus::fromArray($this->data($body));
    }

    /**
     * @return list<Number>
     *
     * @throws RelayException
     */
    public function numbers(): array
    {
        $body = $this->check($this->call(fn (PendingRequest $request): Response => $request->get('/numbers')));
        $numbers = [];

        foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $row) {
            if (is_array($row)) {
                $numbers[] = Number::fromArray($row);
            }
        }

        return $numbers;
    }

    /**
     * One page of the project's opt-out pairs, oldest change first. Read it before reactivating
     * someone, or to catch up after downtime.
     *
     * @throws RelayException
     */
    public function optOuts(?string $to = null, ?string $from = null, ?bool $optedOut = null, ?DateTimeInterface $changedSince = null, int $page = 1): OptOutPage
    {
        $query = array_filter([
            'to' => $to,
            'from' => $from,
            'opted_out' => $optedOut === null ? null : ($optedOut ? 'true' : 'false'),
            'changed_since' => $changedSince?->format(DateTimeInterface::ATOM),
            'page' => $page > 1 ? (string) $page : null,
        ], fn (?string $value): bool => $value !== null);

        return OptOutPage::fromArray($this->check($this->call(fn (PendingRequest $request): Response => $request->get('/opt-outs', $query))));
    }

    /**
     * @param  array<string, string>  $payload
     *
     * @throws RelayException
     */
    private function send(array $payload, string $idempotencyKey, ?string $reference, ?string $from, ?string $requestId): SentMessage
    {
        $headers = ['Idempotency-Key' => $idempotencyKey];

        if ($requestId !== null && preg_match(self::REQUEST_ID_PATTERN, $requestId) === 1) {
            $headers['X-Request-Id'] = $requestId;
        }

        $payload += array_filter(['reference' => $reference, 'from' => $from], fn (?string $value): bool => $value !== null);

        $response = $this->call(fn (PendingRequest $request): Response => $request->withHeaders($headers)->post('/messages', $payload));
        $message = MessageStatus::fromArray($this->data($this->check($response)));

        return new SentMessage($message->id, $message->status, $response->status() === 200, $message);
    }

    /**
     * Runs one request, turning a transport failure or a timeout into `RelayUnavailable`.
     *
     * @param  callable(PendingRequest): Response  $send
     *
     * @throws RelayException
     */
    private function call(callable $send): Response
    {
        if (! $this->configured()) {
            throw new RelayException('The relay is not configured: set RELAY_BASE_URL and RELAY_TOKEN.');
        }

        $request = Http::baseUrl(rtrim($this->setting('base_url'), '/'))
            ->withToken($this->setting('token'))
            ->acceptJson()
            ->asJson()
            ->timeout($this->seconds('timeout', 10))
            ->connectTimeout($this->seconds('connect_timeout', 5));

        try {
            return $send($request);
        } catch (ConnectionException $exception) {
            throw new RelayUnavailable('The relay could not be reached: '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * The decoded body of a success; a typed exception for anything else.
     *
     * @return array<array-key, mixed>
     *
     * @throws RelayException
     */
    private function check(Response $response): array
    {
        $json = $response->json();
        $body = is_array($json) ? $json : [];

        if ($response->successful()) {
            return $body;
        }

        $status = $response->status();
        $message = is_string($body['message'] ?? null) ? $body['message'] : 'The relay answered '.$status.'.';
        $code = is_string($body['code'] ?? null) ? $body['code'] : null;

        throw match (true) {
            $status >= 500 => new RelayUnavailable('The relay answered '.$status.'.'),
            $status === 401 => new Unauthenticated($message, 401, $code, $body),
            $status === 403 => $code === 'project_inactive'
                ? new ProjectPaused($message, 403, $code, $body)
                : new Forbidden($message, 403, $code, $body),
            $status === 404 => new NotFound($message, 404, $code, $body),
            $status === 429 => new RateLimited($message, max(1, (int) $response->header('Retry-After')), $body),
            $status === 422 => $this->unprocessable($message, $code, $body),
            default => new RelayRefused($message, $status, $code, $body),
        };
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function unprocessable(string $message, ?string $code, array $body): RelayRefused
    {
        return match ($code) {
            'recipient_opted_out' => new RecipientOptedOut($message, 422, $code, $body),
            'idempotency_key_reused' => new IdempotencyKeyReused($message, 422, $code, $body),
            'sender_not_in_project' => new SenderNotInProject($message, 422, $code, $body),
            'no_sender' => new NoSender($message, 422, $code, $body),
            default => $this->invalid($message, $body),
        };
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function invalid(string $message, array $body): InvalidRequest
    {
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];
        $field = array_key_first($errors);
        $first = $field === null ? null : $errors[$field];
        $detail = is_array($first) && isset($first[0]) && is_string($first[0]) ? $first[0] : null;

        return new InvalidRequest($message, $field === null ? null : (string) $field, $detail, $body);
    }

    /**
     * @param  array<array-key, mixed>  $body
     * @return array<array-key, mixed>
     */
    private function data(array $body): array
    {
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    private function setting(string $key): string
    {
        $value = config('relay.'.$key);

        return is_string($value) ? $value : '';
    }

    private function seconds(string $key, int $default): int
    {
        $value = config('relay.'.$key);

        return is_numeric($value) ? max(1, (int) $value) : $default;
    }
}
