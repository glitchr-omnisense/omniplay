<?php

namespace Omniplay\Testing;

use Omniplay\Config;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;

/** FixedHosting's factory: "fixed", options services, regions, host, port. */
final class FixedHostingFactory extends ProviderFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omniplay.factory_name' => 'fixed', 'omniplay.factory_title' => 'Fixed hosting', 'omniplay.required_options' => [], 'services' => [], 'regions' => ['local'], 'host' => '127.0.0.1', 'port' => 7000]);
    }

    protected function build(Config $c): ProviderInterface
    {
        $strings = static fn (mixed $list): array => \is_array($list) ? array_values(array_map('strval', array_filter($list, 'is_scalar'))) : [];

        return new FixedHosting($strings($c->get('services')), $strings($c->get('regions')), (string) $c->string('host'), $c->int('port', 7000));
    }
}
