<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Support;

use SensitiveParameter;

/**
 * The relay's callback signature: `Relay-Signature: t={unix seconds},v1={hex}` where `v1` is the
 * HMAC-SHA256 of `{t}.{raw body}` keyed with the project's signing secret. During a secret's
 * rotation the header carries one `v1` per secret, and any one matching is enough.
 */
final class CallbackSignature
{
    public const HEADER = 'Relay-Signature';

    /** Whether `$header` is a valid signature of `$body` under `$secret`, signed within `$toleranceSeconds` of `$now`. */
    public static function verify(string $header, string $body, #[SensitiveParameter] string $secret, int $toleranceSeconds, int $now): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && preg_match('/^\d{1,12}$/', $value) === 1) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || abs($now - $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    /** The header a relay would send for `$body` (tests sign their own callbacks with it). */
    public static function header(string $body, int $timestamp, #[SensitiveParameter] string $secret): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }
}
