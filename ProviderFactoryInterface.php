<?php

namespace Omniplay;

/** Builds a provider from its options (a key, a client id, a Docker host, the services it starts...). */
interface ProviderFactoryInterface extends \Omnisense\Contracts\Provider\ProviderFactoryInterface
{
    /** The name providers are configured with: "steam", "igdb", "docker"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
