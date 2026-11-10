<?php

namespace Omniplay\Policy;

use Omniplay\LibraryInterface;
use Omniplay\ProviderInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A player's library behind its guard: each call is the operation
 * "library".
 *
 * @extends Guarded<LibraryInterface>
 */
final class GuardedLibrary extends Guarded implements LibraryInterface
{
    public function __construct(LibraryInterface&ProviderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function library(string $player): array
    {
        return $this->guard->call('library', fn () => $this->provider->library($player));
    }
}
