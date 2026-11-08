<?php

namespace Omniplay;

/**
 * What a provider is, said before it is asked anything: whether it starts
 * and stops servers, of which services, in which regions, and where it runs
 * them.
 */
final readonly class Capabilities
{
    /**
     * @param bool         $hosting  it starts and stops servers (HostingInterface)
     * @param list<string> $services the services it can start a server of (Service::$name); any, when empty
     * @param list<string> $regions  the regions its servers run in; any asked, when empty
     * @param ?string      $where    where it runs them, for a person to read ("Docker Engine at unix:///var/run/docker.sock")
     */
    public function __construct(
        public bool $hosting = false,
        public array $services = [],
        public array $regions = [],
        public ?string $where = null,
    ) {
    }

    /** Whether it starts a server of this service, in this region (any, when none is asked). */
    public function starts(string $service, ?string $region = null): bool
    {
        return $this->hosting
            && ([] === $this->services || \in_array($service, $this->services, true))
            && (null === $region || [] === $this->regions || \in_array($region, $this->regions, true));
    }

    /** @return list<string> what it can be asked, by contract: hosting */
    public function contracts(): array
    {
        return array_keys(array_filter(['hosting' => $this->hosting]));
    }

    /** @return array{contracts: list<string>, services: list<string>, regions: list<string>, where: ?string} */
    public function toArray(): array
    {
        return ['contracts' => $this->contracts(), 'services' => $this->services, 'regions' => $this->regions, 'where' => $this->where];
    }
}
