<?php

namespace Omniplay\Policy;

use Omniplay\IdentityInterface;
use Omniplay\Model\Identity;
use Omniplay\ProviderInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's player identities behind its guard: each verify() is the
 * operation "verify". platform() is a plain getter, asked of nobody: it
 * passes through unguarded.
 *
 * @extends Guarded<IdentityInterface>
 */
final class GuardedIdentity extends Guarded implements IdentityInterface
{
    public function __construct(IdentityInterface&ProviderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function platform(): string
    {
        return $this->provider->platform();
    }

    public function verify(string $proof): Identity
    {
        return $this->guard->call('verify', fn () => $this->provider->verify($proof));
    }
}
