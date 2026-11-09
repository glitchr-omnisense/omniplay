<?php

namespace Omniplay;

use Omniplay\Model\GameRef;

interface OwnershipInterface
{
    /**
     * Whether the player owns the game (bought it, was given a key for it),
     * as the store answers it.
     *
     * @param string $player what Capabilities::$player says a player is
     */
    public function owns(string $player, GameRef $game): bool;
}
