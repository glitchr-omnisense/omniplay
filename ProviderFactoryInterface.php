<?php

namespace Omniplay;

/** Builds a provider from its options (a Docker host, the services it starts...). */
interface ProviderFactoryInterface
{
    /** The name providers are configured with: "docker", "fixed"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): ProviderInterface;
}
