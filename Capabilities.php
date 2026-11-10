<?php

namespace Omniplay;

use Omniplay\Model\GameRef;
use Omnisense\Contracts\Policy\UsagePolicy;
use Omnisense\Contracts\Provider\CapabilitiesInterface;
use Omniplay\Model\Scheme;

/**
 * What a provider is, said before it is asked anything: what it can read on
 * a store, the identifiers it finds a game by, what a player is to it and
 * what its terms ask of whoever shows its records; whether it starts and
 * stops game servers, of which services, in which regions, and where it runs
 * them; whether it verifies players' proofs from a platform - and its usage
 * policy: its rate limits, what its terms ask of whoever calls it.
 *
 * The policy is omnisense/contracts' UsagePolicy (policy()). The three
 * fields written before it ($attribution, $terms, $keyless) stay as they
 * are given, deprecated: a provider that gives them alone gets a policy made
 * of them, its limits unknown (UsagePolicy::described()). toArray() keeps
 * its shape: the policy is read with policy()->toArray().
 */
final readonly class Capabilities implements CapabilitiesInterface
{
    /** @deprecated since 1.x, read policy()->rights->attribution->text */
    public ?string $attribution;

    /**
     * @var list<string>
     *
     * @deprecated since 1.x, read policy()->provenance (terms, policy)
     */
    public array $terms;

    /** @deprecated since 1.x, read policy()->requirements->key */
    public bool $keyless;

    private UsagePolicy $policy;

    /**
     * @param bool         $search      it searches by title (SearchInterface)
     * @param bool         $catalogue   it gives a game's record (CatalogueInterface)
     * @param bool         $library     it lists a player's games (LibraryInterface)
     * @param bool         $ownership   it says whether a player owns a game (OwnershipInterface)
     * @param list<Scheme> $lookupBy    the identifiers CatalogueInterface finds a game by
     * @param bool         $prices      its records carry a price
     * @param ?string      $player      what a player is to it ("a 64-bit Steam ID"), for library, ownership and identity
     * @param ?string      $attribution what its terms ask to be shown beside its records
     * @param list<string> $terms       the terms whoever runs it must follow (URLs)
     * @param bool         $keyless     its catalogue needs no account nor key
     * @param bool         $hosting     it starts and stops game servers (HostingInterface)
     * @param list<string> $services    the services it can start a server of (Service::$name); any, when empty
     * @param list<string> $regions     the regions its servers run in; any asked, when empty
     * @param ?string      $where       where it runs them or whom it asks, for a person to read ("Docker Engine at unix:///var/run/docker.sock")
     * @param bool         $identity    it verifies players' proofs from a platform (IdentityInterface)
     * @param ?UsagePolicy $policy      its usage policy; made of $attribution, $terms and $keyless when null
     */
    public function __construct(
        public bool $search = false,
        public bool $catalogue = false,
        public bool $library = false,
        public bool $ownership = false,
        public array $lookupBy = [],
        public bool $prices = false,
        public ?string $player = null,
        ?string $attribution = null,
        array $terms = [],
        bool $keyless = false,
        public bool $hosting = false,
        public array $services = [],
        public array $regions = [],
        public ?string $where = null,
        public bool $identity = false,
        ?UsagePolicy $policy = null,
    ) {
        $this->attribution = $attribution;
        $this->terms = $terms;
        $this->keyless = $keyless;
        $this->policy = $policy ?? UsagePolicy::described(UsagePolicy::UNNAMED, $attribution, null, $terms, $keyless);
    }

    public function policy(): UsagePolicy
    {
        return $this->policy;
    }

    /** Whether CatalogueInterface::game() finds this game by this identifier. */
    public function resolves(GameRef $ref): bool
    {
        return $this->catalogue && \in_array($ref->scheme, $this->lookupBy, true);
    }

    /** Whether it starts a server of this service, in this region (any, when none is asked). */
    public function starts(string $service, ?string $region = null): bool
    {
        return $this->hosting
            && ([] === $this->services || \in_array($service, $this->services, true))
            && (null === $region || [] === $this->regions || \in_array($region, $this->regions, true));
    }

    /** @return list<string> what it can be asked, by contract: search, catalogue, library, ownership, hosting, identity */
    public function contracts(): array
    {
        return array_keys(array_filter([
            'search' => $this->search,
            'catalogue' => $this->catalogue,
            'library' => $this->library,
            'ownership' => $this->ownership,
            'hosting' => $this->hosting,
            'identity' => $this->identity,
        ]));
    }

    /**
     * @return array{contracts: list<string>, lookupBy: list<string>, prices: bool, player: ?string, attribution: ?string, terms: list<string>, keyless: bool, services: list<string>, regions: list<string>, where: ?string}
     */
    public function toArray(): array
    {
        return [
            'contracts' => $this->contracts(),
            'lookupBy' => array_map(static fn (Scheme $s) => $s->value, $this->lookupBy),
            'prices' => $this->prices,
            'player' => $this->player,
            'attribution' => $this->attribution,
            'terms' => $this->terms,
            'keyless' => $this->keyless,
            'services' => $this->services,
            'regions' => $this->regions,
            'where' => $this->where,
        ];
    }
}
