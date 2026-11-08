<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/** A player asking for a seat: who, where, on what kind of server, and what their ticket says besides. */
final readonly class SeatRequest
{
    public string $player;

    /**
     * @param ?string              $region where the server should run; any, when null
     * @param list<string>         $tags   what the server must carry (every one)
     * @param array<string, mixed> $claims what the ticket says besides its room and expiry (the player is "sub")
     */
    public function __construct(string $player, public ?string $region = null, public array $tags = [], public array $claims = [])
    {
        $player = trim($player);
        if ('' === $player) {
            throw new InvalidArgumentException('A seat is asked for a named player.');
        }
        $this->player = $player;
    }
}
