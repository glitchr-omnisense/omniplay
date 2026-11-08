<?php

namespace Omniplay\Ticket;

use Omniplay\Exception\InvalidArgumentException;

/**
 * The tickets' one format: base64url(json payload) "." hex(hmac_sha256(that
 * base64url, secret)). The payload names its room and when it expires
 * (`room`, a string; `exp`, Unix seconds); the rest is the application's.
 * A server checks a ticket by itself with the secret it shares with the
 * application, never calling it back. docs/tickets.md is the format in full,
 * Tests/vectors.json the vectors every verifier is checked against.
 *
 * The JSON is PHP's json_encode() with its default flags (slashes and every
 * character past ASCII escaped): the signature is over the base64url text
 * as it stands, so a verifier never encodes anything again, whatever its
 * language. Pure: no state, no framework.
 */
final class TicketSigner
{
    /**
     * A ticket for this payload. It names its room (a non-empty string) and
     * its expiry (an integer): a ticket without them would never be valid.
     *
     * @param array<string, mixed> $payload
     */
    public static function sign(array $payload, string $secret): string
    {
        if (!\is_string($payload['room'] ?? null) || '' === $payload['room']) {
            throw new InvalidArgumentException('A ticket names its room: "room", a non-empty string.');
        }
        if (!\is_int($payload['exp'] ?? null)) {
            throw new InvalidArgumentException('A ticket names its expiry: "exp", an integer (Unix seconds).');
        }
        try {
            $json = json_encode($payload, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException(\sprintf('The ticket\'s payload is not JSON: %s.', $e->getMessage()), 0, $e);
        }

        return self::signJson($json, $secret);
    }

    /**
     * A ticket over this JSON text as it is: what a test of another
     * language's verifier, or a payload encoded elsewhere, signs.
     */
    public static function signJson(string $json, string $secret): string
    {
        if ('' === $secret) {
            throw new InvalidArgumentException('A ticket is signed with a non-empty secret.');
        }
        $body = self::base64url($json);

        return $body.'.'.hash_hmac('sha256', $body, $secret);
    }

    /**
     * A ticket's payload when its signature holds, it is for this room and
     * it has not expired (`exp` before $now, a Unix time, the clock's when
     * null); null otherwise. An empty secret verifies nothing; `room` must be
     * a string equal to the one asked; `exp` a number, its integer part
     * compared (an expiry equal to now still holds).
     *
     * @return array<string, mixed>|null
     */
    public static function verify(string $token, string $secret, string $room, ?int $now = null): ?array
    {
        if ('' === $secret) {
            return null;
        }
        $dot = strrpos($token, '.');
        if (false === $dot) {
            return null;
        }
        $body = substr($token, 0, $dot);
        if (!hash_equals(hash_hmac('sha256', $body, $secret), substr($token, $dot + 1))) {
            return null;
        }
        $payload = json_decode((string) base64_decode(strtr($body, '-_', '+/'), false), true);
        if (!\is_array($payload) || ($payload['room'] ?? null) !== $room) {
            return null;
        }
        $exp = $payload['exp'] ?? null;
        if ((!\is_int($exp) && !\is_float($exp)) || (int) $exp < ($now ?? time())) {
            return null;
        }
        $claims = [];
        foreach ($payload as $key => $value) {
            $claims[(string) $key] = $value;
        }

        return $claims;
    }

    private static function base64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
