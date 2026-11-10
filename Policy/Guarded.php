<?php

namespace Omniplay\Policy;

use Omnisense\Contracts\Policy\PolicyGuard;
use Omniplay\Capabilities;
use Omniplay\ProviderInterface;

/**
 * A provider behind its guard: what every Guarded* shares - its name, title
 * and capabilities as they are, the guard, the provider itself (inner()).
 *
 * @template P of object the contract it keeps (a provider's contracts do not extend ProviderInterface)
 */
abstract class Guarded implements ProviderInterface
{
    /** @param P&ProviderInterface $provider */
    public function __construct(protected readonly ProviderInterface $provider, protected readonly PolicyGuard $guard)
    {
    }

    public function getName(): string
    {
        return $this->provider->getName();
    }

    public function getTitle(): string
    {
        return $this->provider->getTitle();
    }

    public function capabilities(): Capabilities
    {
        return $this->provider->capabilities();
    }

    /** @return P&ProviderInterface the provider, unguarded */
    public function inner(): ProviderInterface
    {
        return $this->provider;
    }

    public function guard(): PolicyGuard
    {
        return $this->guard;
    }
}
