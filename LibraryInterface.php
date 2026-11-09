<?php

namespace Omniplay;

use Omniplay\Model\OwnedGame;

interface LibraryInterface
{
    /**
     * The games a player owns, as far as the store lets them be read (a
     * private profile reads as an empty library on some stores).
     *
     * @param string $player what Capabilities::$player says a player is
     *
     * @return list<OwnedGame>
     */
    public function library(string $player): array;
}
