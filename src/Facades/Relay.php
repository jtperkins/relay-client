<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Facades;

use DateTimeInterface;
use Illuminate\Support\Facades\Facade;
use Jtperkins\RelayClient\Data\MessageStatus;
use Jtperkins\RelayClient\Data\Number;
use Jtperkins\RelayClient\Data\OptOutPage;
use Jtperkins\RelayClient\Data\SentMessage;
use Jtperkins\RelayClient\RelayClient;

/**
 * @method static bool configured()
 * @method static SentMessage sendSms(string $to, string $body, string $idempotencyKey, ?string $reference = null, ?string $from = null, ?string $requestId = null)
 * @method static SentMessage sendEmail(string $to, string $subject, string $body, string $idempotencyKey, ?string $reference = null, ?string $from = null, ?string $requestId = null, ?string $html = null)
 * @method static MessageStatus message(string $id)
 * @method static list<Number> numbers()
 * @method static OptOutPage optOuts(?string $to = null, ?string $from = null, ?bool $optedOut = null, ?DateTimeInterface $changedSince = null, int $page = 1)
 *
 * @see RelayClient
 */
final class Relay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return RelayClient::class;
    }
}
