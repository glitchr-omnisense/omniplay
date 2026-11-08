<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/**
 * A player asking for a seat: who, where, on what kind of server, and what
 * their ticket says besides. The player is the application's string, or an
 * Identity a platform verified (IdentityInterface): then the player's id is
 * Identity::player() ("steam:76561197960287930") and $identity keeps it.
 */
final readonly class SeatRequest
{
    public string $player;

    /** The platform's verified player, when the request was made with one. */
    public ?Identity $identity;

    /**
     * @param string|Identity      $player the player's id, or the Identity a platform verified
     * @param ?string              $region where the server should run; any, when null
     * @param list<string>         $tags   what the server must carry (every one)
     * @param array<string, mixed> $claims what the ticket says besides its room and expiry (the player is "sub")
     */
    public function __construct(string|Identity $player, public ?string $region = null, public array $tags = [], public array $claims = [])
    {
        $this->identity = $player instanceof Identity ? $player : null;
        $player = trim($player instanceof Identity ? $player->player() : $player);
        if ('' === $player) {
            throw new InvalidArgumentException('A seat is asked for a named player.');
        }
        $this->player = $player;
    }
}
