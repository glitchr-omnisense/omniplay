<?php

namespace Omniplay\Policy;

use Omniplay\Model\GameRef;
use Omniplay\OwnershipInterface;
use Omniplay\ProviderInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's ownership checks behind its guard: each call is the operation
 * "owns".
 *
 * @extends Guarded<OwnershipInterface>
 */
final class GuardedOwnership extends Guarded implements OwnershipInterface
{
    public function __construct(OwnershipInterface&ProviderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function owns(string $player, GameRef $game): bool
    {
        return $this->guard->call('owns', fn () => $this->provider->owns($player, $game));
    }
}
