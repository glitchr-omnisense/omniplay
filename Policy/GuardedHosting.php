<?php

namespace Omniplay\Policy;

use Omniplay\HostingInterface;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\ProviderInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's game servers behind its guard: each call is the operation of its
 * method's name ("start", "stop", "running").
 *
 * @extends Guarded<HostingInterface>
 */
final class GuardedHosting extends Guarded implements HostingInterface
{
    public function __construct(HostingInterface&ProviderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function start(Service $service, string $secret, ?string $region = null, array $tags = []): Server
    {
        return $this->guard->call('start', fn () => $this->provider->start($service, $secret, $region, $tags));
    }

    public function stop(Server $server): void
    {
        $this->guard->call('stop', fn () => $this->provider->stop($server));
    }

    public function running(?string $service = null): array
    {
        return $this->guard->call('running', fn () => $this->provider->running($service));
    }
}
