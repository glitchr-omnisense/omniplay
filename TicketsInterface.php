<?php

namespace Omniplay;

use Omniplay\Model\Service;
use Omniplay\Model\Ticket;

/**
 * Tickets to a service's servers: minted by the application, checked by the
 * server by itself with the secret they share (Ticket\TicketSigner's
 * format). verify() is the application's side of the same check: a server
 * calling the application back with its players' tickets is what tickets
 * avoid, but an application may read one it handed out.
 */
interface TicketsInterface
{
    /**
     * A ticket to this room of the service, living the service's ttl.
     *
     * @param array<string, mixed> $claims the application's own; "room" and "exp" are the ticket's
     */
    public function mint(Service $service, string $room, array $claims = []): Ticket;

    /** The ticket, when the token holds for this room of the service; null otherwise. */
    public function verify(Service $service, string $token, string $room): ?Ticket;
}
