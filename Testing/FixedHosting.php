<?php

namespace Omniplay\Testing;

use Omniplay\Capabilities;
use Omniplay\Exception\NotSupportedException;
use Omniplay\HostingInterface;
use Omniplay\Model\Health;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\ProviderInterface;

/**
 * A hosting provider that starts nothing: each server it is asked for is
 * given the next port on its host ("127.0.0.1:7001", ":7002"...), Starting,
 * and kept in $started with the secret it was handed; stop() moves it to
 * $stopped. An application's tests, the harness's bare script.
 */
final class FixedHosting implements ProviderInterface, HostingInterface
{
    /** @var array<string, Server> */
    public array $started = [];

    /** @var array<string, Server> */
    public array $stopped = [];

    /** @var array<string, string> the secret each server was started with, by its id */
    public array $secrets = [];

    /**
     * @param list<string> $services the services it starts (every one, when empty)
     * @param list<string> $regions
     */
    public function __construct(private readonly array $services = [], private readonly array $regions = ['local'], private readonly string $host = '127.0.0.1', private readonly int $port = 7000, private readonly string $name = 'fixed-hosting')
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return 'Fixed hosting';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(hosting: true, services: $this->services, regions: $this->regions, where: 'nowhere: '.$this->host.', in memory');
    }

    public function start(Service $service, string $secret, ?string $region = null, array $tags = []): Server
    {
        if (([] !== $this->services && !\in_array($service->name, $this->services, true)) || (null !== $region && !\in_array($region, $this->regions, true))) {
            throw NotSupportedException::service($this->getTitle(), $service->name, $region);
        }
        $n = \count($this->started) + 1;
        $id = \sprintf('%s-%d', $service->name, $n);
        $server = new Server($id, $service->name, \sprintf('%s:%d', $this->host, $this->port + $n), $service->capacity, 0, Health::Starting, null, $region ?? $this->regions[0] ?? null, $tags, $service->engine, $this->name, 'fixed-'.$n);
        $this->started[$id] = $server;
        $this->secrets[$id] = $secret;

        return $server;
    }

    public function stop(Server $server): void
    {
        if (isset($this->started[$server->id])) {
            $this->stopped[$server->id] = $this->started[$server->id];
            unset($this->started[$server->id]);
        }
    }

    public function running(?string $service = null): array
    {
        return array_values(array_filter($this->started, static fn (Server $server) => null === $service || $service === $server->service));
    }
}
