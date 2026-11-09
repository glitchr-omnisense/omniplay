<?php

namespace Omniplay;

use Omniplay\Model\Game;
use Omniplay\Model\Query;

interface SearchInterface
{
    /**
     * Games whose title matches, best first, as short as the provider's
     * search answers them (ask the catalogue for a whole record).
     *
     * @return list<Game>
     */
    public function search(Query $query): array;
}
