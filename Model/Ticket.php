<?php

namespace Omniplay\Model;

/**
 * A signed ticket and what it says: the room it opens, when it expires, the
 * application's own claims. The token is what the player presents; the
 * server reads the rest from it by itself.
 */
final readonly class Ticket implements \Stringable
{
    /** @param array<string, mixed> $claims the application's own, room and exp left out */
    public function __construct(public string $token, public string $room, public \DateTimeImmutable $expires, public array $claims = [])
    {
    }

    public function __toString(): string
    {
        return $this->token;
    }

    /** @return array{token: string, room: string, expires: string, claims: array<string, mixed>} */
    public function toArray(): array
    {
        return ['token' => $this->token, 'room' => $this->room, 'expires' => $this->expires->format(\DATE_ATOM), 'claims' => $this->claims];
    }
}
