<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Data;

use Carbon\CarbonImmutable;

/**
 * Narrows the relay's decoded JSON, so a DTO's constructor takes real types and a missing field is
 * a default, never a TypeError. Internal to the Data classes.
 *
 * @internal
 */
final class Cast
{
    public static function string(mixed $value, string $default = ''): string
    {
        return is_string($value) ? $value : (is_int($value) ? (string) $value : $default);
    }

    public static function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }

    public static function bool(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    public static function date(mixed $value): CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : CarbonImmutable::createFromTimestampUTC(0);
    }

    public static function nullableDate(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }
}
