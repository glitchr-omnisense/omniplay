<?php

namespace Omniplay\Testing;

use Omniplay\Config;
use Omniplay\Model\Game;
use Omniplay\Model\OwnedGame;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;

/** FixedProvider's factory: "fixed", answering from the games and libraries it holds. */
final class FixedProviderFactory extends ProviderFactory
{
    /**
     * @param list<Game>                     $games
     * @param array<string, list<OwnedGame>> $libraries
     */
    public function __construct(private readonly array $games = [], private readonly array $libraries = [])
    {
    }

    protected function populate(Config $c): void
    {
        $c->defaults(['omniplay.factory_name' => 'fixed', 'omniplay.factory_title' => 'Fixed games', 'omniplay.required_options' => []]);
    }

    protected function build(Config $c): ProviderInterface
    {
        return new FixedProvider($this->games, $this->libraries);
    }
}
