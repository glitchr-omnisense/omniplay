<?php

namespace Omniplay;

use Omniplay\Exception\InvalidConfigException;
use Omniplay\Exception\NotSupportedException;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Policy\GuardedCatalogue;
use Omniplay\Policy\GuardedHosting;
use Omniplay\Policy\GuardedIdentity;
use Omniplay\Policy\GuardedLibrary;
use Omniplay\Policy\GuardedOwnership;
use Omniplay\Policy\GuardedSearch;
use Omnisense\Contracts\Policy\RateLimiterInterface;
use Omnisense\Contracts\Provider\ProviderInterface as BaseProvider;
use Omnisense\Contracts\Provider\Registry as BaseRegistry;

/**
 * The application's providers by name, each built once from its factory and
 * options, whatever contracts each implements, each call through a contract
 * guarded by the provider's usage policy:
 *
 *   $registry = new Registry([new SteamFactory($http), new IgdbFactory($http), new DockerFactory()], [
 *       'steam' => ['factory' => 'steam', 'options' => ['key' => '...', 'appid' => 480]],
 *       'igdb' => [
 *           'factory' => 'igdb',
 *           'options' => ['client_id' => '...', 'client_secret' => '...'],
 *           'policy' => ['rateLimits' => [['max' => 2, 'window' => 'second']]],
 *       ],
 *       'local' => ['factory' => 'docker', 'options' => ['host' => 'unix:///var/run/docker.sock', 'services' => [...]]],
 *   ], $limiter);
 *
 *   $registry->catalogue('steam')->game(GameRef::steam(620), 'french', 'FR');
 *   $registry->game(GameRef::gog(1207658924));   // the first provider that finds it
 *   $registry->hostingFor('game')?->start($service, $secret);
 *   $registry->identityFor('steam')?->verify($ticket);
 *
 * search(), catalogue(), library() and ownership() give the provider behind
 * its guard (Policy\Guarded*): each call is checked against the policy's
 * rate limits for its operation - the contract's method name, "search",
 * "game", "library", "owns" - then counted, and refused with
 * Exception\UsageLimitReachedException once a limit is reached or while
 * the provider asks to wait; game() asks through them too. hosting(),
 * hostingFor(), identity() and identityFor() give the provider behind its
 * guard ("start", "stop", "running", "verify") when its policy, as the site
 * runs it, limits one of those operations, the provider itself otherwise:
 * a Docker Engine of one's own counts nothing, and its HTTP client honours
 * a 429's wait all the same. get() gives the provider itself, unguarded.
 * "policy" tightens the provider's policy, never loosens it; "usage" says
 * what the site is (omnisense/contracts' Registry).
 */
final class Registry extends BaseRegistry
{
    /** @var string */
    public const PREFIX = Config::PREFIX;

    /** @var array<string, ProviderInterface> the guarded contracts, by "contract name" */
    private array $guarded = [];

    /**
     * @param iterable<ProviderFactoryInterface>                                                                                         $factories
     * @param array<string, array{factory: string, options?: array<string, mixed>, policy?: array<string, mixed>, usage?: array<string, mixed>}> $config
     * @param ?RateLimiterInterface                                                                                                      $limiter   where every provider's calls are counted: an in-memory one by default, a shared one for an application served by many processes
     */
    public function __construct(iterable $factories, array $config, ?RateLimiterInterface $limiter = null)
    {
        parent::__construct($factories, $config, $limiter);
    }

    public function get(string $name): ProviderInterface
    {
        $provider = parent::get($name);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" game provider (%s) is no omniplay provider.', $name, $provider::class));
    }

    public function search(string $name): SearchInterface
    {
        $guarded = $this->guarded['search '.$name] ??= new GuardedSearch($this->provider($name, SearchInterface::class, 'search'), $this->guard($name));

        return $guarded instanceof SearchInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    public function catalogue(string $name): CatalogueInterface
    {
        $guarded = $this->guarded['catalogue '.$name] ??= new GuardedCatalogue($this->provider($name, CatalogueInterface::class, 'give a game\'s record'), $this->guard($name));

        return $guarded instanceof CatalogueInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    public function library(string $name): LibraryInterface
    {
        $provider = $this->get($name);
        // Offered only when it can be given: a library that needs a key the provider was not configured with is not.
        if (!$provider instanceof LibraryInterface || !$provider->capabilities()->library) {
            throw NotSupportedException::capability($provider, $name, 'list a player\'s games');
        }
        $guarded = $this->guarded['library '.$name] ??= new GuardedLibrary($provider, $this->guard($name));

        return $guarded instanceof LibraryInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    public function ownership(string $name): OwnershipInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof OwnershipInterface || !$provider->capabilities()->ownership) {
            throw NotSupportedException::capability($provider, $name, 'say whether a player owns a game');
        }
        $guarded = $this->guarded['ownership '.$name] ??= new GuardedOwnership($provider, $this->guard($name));

        return $guarded instanceof OwnershipInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /**
     * The game from the first provider, in the configuration's order (or the
     * order named), that finds it by this identifier: a Steam app id read
     * from IGDB asked of Steam's store. Each provider is asked through its
     * guard.
     */
    public function game(GameRef $ref, ?string $language = null, ?string $country = null, string ...$names): ?Game
    {
        foreach ($names ?: $this->names() as $name) {
            $provider = $this->get($name);
            if ($provider instanceof CatalogueInterface && $provider->capabilities()->resolves($ref) && null !== $game = $this->catalogue($name)->game($ref, $language, $country)) {
                return $game;
            }
        }

        return null;
    }

    /** Behind its guard when its policy limits "start", "stop" or "running"; the provider itself otherwise. */
    public function hosting(string $name): HostingInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof HostingInterface || !$provider->capabilities()->hosting) {
            throw NotSupportedException::capability($provider, $name, 'start servers');
        }
        if (!$this->counts($name, 'start', 'stop', 'running')) {
            return $provider;
        }
        $guarded = $this->guarded['hosting '.$name] ??= new GuardedHosting($provider, $this->guard($name));

        return $guarded instanceof HostingInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /**
     * The first provider, in the configuration's order (or the order named),
     * that starts a server of this service in this region, as hosting()
     * gives it.
     */
    public function hostingFor(string $service, ?string $region = null, string ...$names): ?HostingInterface
    {
        foreach ($names ?: $this->names() as $name) {
            $provider = $this->get($name);
            if ($provider instanceof HostingInterface && $provider->capabilities()->starts($service, $region)) {
                return $this->hosting($name);
            }
        }

        return null;
    }

    /** Behind its guard when its policy limits "verify"; the provider itself otherwise. */
    public function identity(string $name): IdentityInterface
    {
        $provider = $this->get($name);
        if (!$provider instanceof IdentityInterface || !$provider->capabilities()->identity) {
            throw NotSupportedException::capability($provider, $name, 'verify players');
        }
        if (!$this->counts($name, 'verify')) {
            return $provider;
        }
        $guarded = $this->guarded['identity '.$name] ??= new GuardedIdentity($provider, $this->guard($name));

        return $guarded instanceof IdentityInterface ? $guarded : throw new \LogicException('A guarded contract of another kind.');
    }

    /**
     * The first provider, in the configuration's order (or the order named),
     * that verifies the players of this platform, as identity() gives it;
     * null when none does - the application then names its players itself.
     */
    public function identityFor(string $platform, string ...$names): ?IdentityInterface
    {
        foreach ($names ?: $this->names() as $name) {
            $provider = $this->get($name);
            if ($provider instanceof IdentityInterface && $provider->capabilities()->identity && $provider->platform() === strtolower($platform)) {
                return $this->identity($name);
            }
        }

        return null;
    }

    /**
     * The configured providers' names, in the configuration's order; those
     * keeping a contract ("search", "catalogue", "hosting"...) when one is
     * named.
     *
     * @return list<string>
     */
    public function names(?string $contract = null): array
    {
        return parent::names($contract);
    }

    /** The factory of a configured provider: an unknown one is a "game provider"'s, as omniplay always said. */
    protected function factory(string $name): \Omnisense\Contracts\Provider\ProviderFactoryInterface
    {
        $provider = $this->config[$name] ?? throw new InvalidConfigException(\sprintf('No "%s" game provider; configured: %s.', $name, implode(', ', array_keys($this->config)) ?: 'none'));

        return $this->factories[$provider['factory']] ?? throw new InvalidConfigException(\sprintf('No "%s" factory for the "%s" game provider; installed: %s.', $provider['factory'], $name, implode(', ', array_keys($this->factories)) ?: 'none'));
    }

    /** Whether the provider's policy, as the site runs it, limits one of these operations. */
    private function counts(string $name, string ...$operations): bool
    {
        $policy = $this->policy($name);
        foreach ($operations as $operation) {
            if ([] !== $policy->limitsFor($operation)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A provider that keeps a contract, or NotSupportedException.
     *
     * @template T of object
     *
     * @param class-string<T> $contract
     *
     * @return T&ProviderInterface
     */
    private function provider(string $name, string $contract, string $capability): object
    {
        $provider = $this->contract($name, $contract, $capability);

        return $provider instanceof ProviderInterface ? $provider : throw new InvalidConfigException(\sprintf('The "%s" game provider (%s) is no omniplay provider.', $name, $provider::class));
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }

    protected function notSupported(BaseProvider $provider, string $name, string $capability): \Throwable
    {
        return NotSupportedException::capability($provider, $name, $capability);
    }
}
