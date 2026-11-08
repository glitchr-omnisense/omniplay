<?php

namespace Omniplay\Storage;

use Omniplay\Model\Server;

/**
 * Where the fleet keeps its servers: InMemoryStore for tests and a single
 * process, the application's own otherwise (a table, a Redis hash: a Server
 * goes in as toArray() and comes back through Server::fromArray()).
 */
interface StoreInterface
{
    /** Keeps the server, replacing the one of the same id. */
    public function save(Server $server): void;

    public function find(string $id): ?Server;

    /** @return list<Server> every server kept, of this service only when one is named */
    public function all(?string $service = null): array;

    public function delete(string $id): void;
}
