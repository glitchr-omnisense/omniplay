<?php

namespace Omniplay;

use Omniplay\Model\Health;
use Omniplay\Model\Server;

/**
 * The servers the application knows: registered when they start, kept
 * current by their heartbeats, retired when they fall silent.
 */
interface FleetInterface
{
    /** Keeps the server, its heartbeat stamped now. */
    public function register(Server $server): Server;

    /**
     * What the server says of itself: its players and its health, stamped
     * now. Null when the fleet does not know it (retired, or registered
     * before a store was lost): it registers again.
     */
    public function heartbeat(string $id, int $players, Health $health = Health::Healthy): ?Server;

    public function get(string $id): ?Server;

    /** @return list<Server> every server of the service, in the region when one is named, whatever its health */
    public function servers(string $service, ?string $region = null): array;

    /** @return list<Server> the servers of the service that are healthy and heard from within the fleet's ttl */
    public function healthy(string $service, ?string $region = null): array;

    /**
     * @return list<Server> the servers of the service a player may be seated on now: a seat left, and healthy within the
     *                      ttl or starting within the startup's
     */
    public function available(string $service, ?string $region = null): array;

    /**
     * Counts one more player on the server, when it has a seat left (the
     * next heartbeat gives the true count).
     */
    public function occupy(string $id): ?Server;

    /**
     * Removes the servers not heard from within the ttl (a longer one while
     * starting) and the ones that said they are down, and returns them: the
     * application stops those a hosting provider started.
     *
     * @return list<Server>
     */
    public function retireStale(): array;

    public function remove(string $id): void;
}
