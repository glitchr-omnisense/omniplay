<?php

namespace Omniplay;

/**
 * One way of running game servers, configured: a Docker Engine, a test's
 * fixed hosting... Its Capabilities say what it can be asked - start and
 * stop a server of a service (HostingInterface) - which services it knows
 * and in which regions it runs them.
 */
interface ProviderInterface
{
    /** The factory's name: "docker", "fixed"... */
    public function getName(): string;

    public function getTitle(): string;

    public function capabilities(): Capabilities;
}
