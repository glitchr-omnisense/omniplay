<?php

namespace Omniplay\Policy;

use Omniplay\CatalogueInterface;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\ProviderInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's store records behind its guard: each call is the operation
 * "game".
 *
 * @extends Guarded<CatalogueInterface>
 */
final class GuardedCatalogue extends Guarded implements CatalogueInterface
{
    public function __construct(CatalogueInterface&ProviderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function game(GameRef $ref, ?string $language = null, ?string $country = null): ?Game
    {
        return $this->guard->call('game', fn () => $this->provider->game($ref, $language, $country));
    }
}
