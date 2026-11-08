<?php

namespace Omniplay;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Model\Health;
use Omniplay\Model\Server;
use Omniplay\Storage\StoreInterface;

/**
 * FleetInterface over a store. A server is healthy while it says so and was
 * heard from within $ttl seconds; one starting is given $startup seconds
 * before its first heartbeat.
 */
final class Fleet implements FleetInterface
{
    /** @var \Closure(): int */
    private readonly \Closure $now;

    /**
     * @param int             $ttl     seconds without a heartbeat before a server is stale
     * @param int             $startup seconds a starting server is given
     * @param ?\Closure(): int $now    the clock, in Unix seconds (time() when null)
     */
    public function __construct(private readonly StoreInterface $store, private readonly int $ttl = 30, private readonly int $startup = 120, ?\Closure $now = null)
    {
        if ($ttl < 1 || $startup < 1) {
            throw new InvalidArgumentException(\sprintf('A fleet waits at least a second for a heartbeat, not %d and %d.', $ttl, $startup));
        }
        $this->now = $now ?? time(...);
    }

    public function register(Server $server): Server
    {
        $server = $server->withHeartbeat($server->players, $server->health, $this->now());
        $this->store->save($server);

        return $server;
    }

    public function heartbeat(string $id, int $players, Health $health = Health::Healthy): ?Server
    {
        $server = $this->store->find($id);
        if (null === $server) {
            return null;
        }
        $server = $server->withHeartbeat(max(0, $players), $health, $this->now());
        $this->store->save($server);

        return $server;
    }

    public function get(string $id): ?Server
    {
        return $this->store->find($id);
    }

    public function servers(string $service, ?string $region = null): array
    {
        return array_values(array_filter($this->store->all($service), static fn (Server $server) => null === $region || $region === $server->region));
    }

    public function healthy(string $service, ?string $region = null): array
    {
        $now = $this->now();

        return array_values(array_filter($this->servers($service, $region), fn (Server $server) => Health::Healthy === $server->health && $server->silence($now) <= $this->ttl));
    }

    public function available(string $service, ?string $region = null): array
    {
        $now = $this->now();

        return array_values(array_filter($this->servers($service, $region), fn (Server $server) => $server->health->seats() && $server->free() > 0
            && $server->silence($now) <= (Health::Starting === $server->health ? $this->startup : $this->ttl)));
    }

    public function occupy(string $id): ?Server
    {
        $server = $this->store->find($id);
        if (null === $server || $server->free() < 1 || !$server->health->seats()) {
            return null;
        }
        $server = $server->withPlayers($server->players + 1);
        $this->store->save($server);

        return $server;
    }

    public function retireStale(): array
    {
        $now = $this->now();
        $retired = [];
        foreach ($this->store->all() as $server) {
            if (Health::Down === $server->health || $server->silence($now) > (Health::Starting === $server->health ? $this->startup : $this->ttl)) {
                $this->store->delete($server->id);
                $retired[] = $server;
            }
        }

        return $retired;
    }

    public function remove(string $id): void
    {
        $this->store->delete($id);
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('@'.($this->now)());
    }
}
