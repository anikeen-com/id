<?php

namespace Anikeen\Id\Webhooks;

use Anikeen\Id\Exceptions\SignatureVerificationException;

/**
 * Verifies webhooks from Anikeen ID, like Stripe's Webhook::constructEvent().
 *
 * Anikeen ID signs every request with your endpoint's signing secret (whsec_…):
 * `Anikeen-Signature: t=<unix time>,v1=<hex HMAC-SHA256 of "<t>.<raw body>">`.
 */
final class Webhook
{
    /** Requests older (or newer) than this many seconds are rejected to prevent replays. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verifies the signature and returns the event: ['id', 'type', 'created', 'data' => ['object' => …]].
     *
     * @param  string  $payload  the raw request body (not re-encoded JSON)
     * @param  string|null  $signatureHeader  the Anikeen-Signature header
     *
     * @throws SignatureVerificationException
     */
    public static function constructEvent(string $payload, ?string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): array
    {
        self::verify($payload, $signatureHeader, $secret, $tolerance);

        $event = json_decode($payload, true);

        if (!is_array($event) || !isset($event['id'], $event['type'])) {
            throw new SignatureVerificationException('The webhook payload is not a valid event.');
        }

        return $event;
    }

    /**
     * @throws SignatureVerificationException
     */
    public static function verify(string $payload, ?string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): void
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', (string) $signatureHeader) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

            if ($key === 't' && ctype_digit((string) $value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value) {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === []) {
            throw new SignatureVerificationException('The Anikeen-Signature header is missing or malformed.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        if (!array_filter($signatures, fn (string $signature) => hash_equals($expected, $signature))) {
            throw new SignatureVerificationException('The webhook signature does not match. Check the signing secret.');
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            throw new SignatureVerificationException('The webhook timestamp is outside the tolerance.');
        }
    }
}
