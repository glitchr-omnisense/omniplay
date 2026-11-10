<?php

namespace Omniplay\Policy;

use Omniplay\Model\Query;
use Omniplay\ProviderInterface;
use Omniplay\SearchInterface;
use Omnisense\Contracts\Policy\PolicyGuard;

/**
 * A provider's search behind its guard: each call is the operation
 * "search".
 *
 * @extends Guarded<SearchInterface>
 */
final class GuardedSearch extends Guarded implements SearchInterface
{
    public function __construct(SearchInterface&ProviderInterface $provider, PolicyGuard $guard)
    {
        parent::__construct($provider, $guard);
    }

    public function search(Query $query): array
    {
        return $this->guard->call('search', fn () => $this->provider->search($query));
    }
}
