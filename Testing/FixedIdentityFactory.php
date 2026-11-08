<?php

namespace Omniplay\Testing;

use Omniplay\Config;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;

/** FixedIdentity's factory: "fixed-identity", options players (the player's id by proof), platform ("test"). */
final class FixedIdentityFactory extends ProviderFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omniplay.factory_name' => 'fixed-identity', 'omniplay.factory_title' => 'Fixed identity', 'omniplay.required_options' => [], 'players' => [], 'platform' => 'test']);
    }

    protected function build(Config $c): ProviderInterface
    {
        $players = [];
        $given = $c->get('players');
        foreach (\is_array($given) ? $given : [] as $proof => $id) {
            if (\is_scalar($id)) {
                $players[(string) $proof] = (string) $id;
            }
        }

        return new FixedIdentity($players, (string) $c->string('platform'));
    }
}
