<?php

namespace Omniplay\Model;

/**
 * A player's seat: the server, the ticket it lets them in with, and whether
 * the server was started for them (then it may still be booting: the client
 * retries the address until the ticket expires).
 */
final readonly class Seat
{
    public function __construct(public string $player, public Server $server, public Ticket $ticket, public bool $started = false)
    {
    }

    /**
     * What the application hands the player's client: where to connect, the
     * ticket and its expiry. Nothing secret.
     *
     * @return array{address: string, ticket: string, expires: int, server: string, service: string, region: ?string, engine: ?string, started: bool}
     */
    public function toArray(): array
    {
        return [
            'address' => $this->server->address,
            'ticket' => $this->ticket->token,
            'expires' => $this->ticket->expires->getTimestamp(),
            'server' => $this->server->id,
            'service' => $this->server->service,
            'region' => $this->server->region,
            'engine' => $this->server->engine,
            'started' => $this->started,
        ];
    }
}
