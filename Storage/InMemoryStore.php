<?php

namespace Omniplay\Storage;

use Omniplay\Model\Server;

/** The servers in an array: a test's store, or a single long-running process's. Gone with the process. */
final class InMemoryStore implements StoreInterface
{
    /** @var array<string, Server> */
    private array $servers = [];

    public function save(Server $server): void
    {
        $this->servers[$server->id] = $server;
    }

    public function find(string $id): ?Server
    {
        return $this->servers[$id] ?? null;
    }

    public function all(?string $service = null): array
    {
        return array_values(array_filter($this->servers, static fn (Server $server) => null === $service || $service === $server->service));
    }

    public function delete(string $id): void
    {
        unset($this->servers[$id]);
    }
}
