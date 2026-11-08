<?php

namespace Omniplay;

use Omniplay\Model\Identity;

/**
 * Verifies a player's proof from a platform - a Steam session ticket, a
 * store's signed token - with that platform, on the application's side:
 * implemented by providers (omniplay/steam). The answer is the player the
 * platform vouches for, or a refusal that says why; never a guess.
 *
 * Nothing in omniplay requires one: a SeatRequest names its player by any
 * string, or by a verified Identity when the application has one.
 */
interface IdentityInterface
{
    /** The platform whose players it verifies, as Identity::$platform names it: "steam"... */
    public function platform(): string;

    /**
     * The player the proof names, verified by the platform.
     *
     * @throws \Omniplay\Exception\IdentityRefusedException when the platform refuses the proof, or it is not one (the message says why)
     * @throws \Omniplay\Exception\ProviderException        when the platform answers with an error, or with no verdict
     * @throws \Omniplay\Exception\UnreachableException     when the platform cannot be reached
     */
    public function verify(string $proof): Identity;
}
