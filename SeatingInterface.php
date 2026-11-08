<?php

namespace Omniplay;

use Omniplay\Model\Seat;
use Omniplay\Model\SeatRequest;
use Omniplay\Model\Service;

/** Seats a player on a fitting server of a service, with a ticket to it. */
interface SeatingInterface
{
    /** The seat, or null when no server fits and none could be started. */
    public function seat(Service $service, SeatRequest $request): ?Seat;
}
