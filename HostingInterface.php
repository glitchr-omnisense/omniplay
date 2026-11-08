<?php

namespace Omniplay;

use Omniplay\Model\Server;
use Omniplay\Model\Service;

/**
 * Starts and stops game servers: implemented by providers (a Docker Engine,
 * a cloud's API). What a server runs is the provider's configuration (an
 * image, a command), keyed by the service's name; omniplay knows no engine.
 */
interface HostingInterface
{
    /**
     * Starts a server of the service, its ticket secret handed to it (its
     * environment, for a container), and returns it Starting: not yet
     * registered in any fleet. The secret is in nothing returned.
     *
     * @param list<string> $tags what the server will carry
     *
     * @throws \Omniplay\Exception\NotSupportedException when it starts no server of this service, or not in this region
     */
    public function start(Service $service, string $secret, ?string $region = null, array $tags = []): Server;

    /** Stops the server it started (nothing when it is gone already). */
    public function stop(Server $server): void;

    /**
     * @return list<Server> the servers it runs, of this service only when one is named: what a fleet is rebuilt from
     */
    public function running(?string $service = null): array;
}
