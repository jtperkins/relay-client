<?php

declare(strict_types=1);

namespace Jtperkins\RelayClient\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Jtperkins\RelayClient\Support\CallbackSignature;
use Symfony\Component\HttpFoundation\Response;

/**
 * Verifies the relay's `Relay-Signature` over the raw body, against the current and (during a
 * rotation) the previous secret, within `relay.signature_tolerance` seconds of this app's clock.
 * Anything else, and an app with no secret set, is a bare 403.
 */
final class VerifyRelaySignature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secrets = array_values(array_filter([
            $this->config('relay.callback_secret'),
            $this->config('relay.callback_secret_previous'),
        ], fn (string $secret): bool => $secret !== ''));

        if ($secrets === []) {
            abort(403);
        }

        $header = (string) $request->header(CallbackSignature::HEADER, '');
        $body = $request->getContent();
        $now = now()->getTimestamp();
        $tolerance = is_numeric(config('relay.signature_tolerance')) ? (int) config('relay.signature_tolerance') : 300;

        foreach ($secrets as $secret) {
            if (CallbackSignature::verify($header, $body, $secret, $tolerance, $now)) {
                return $next($request);
            }
        }

        abort(403);
    }

    private function config(string $key): string
    {
        $value = config($key);

        return is_string($value) ? $value : '';
    }
}
