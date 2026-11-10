<?php

namespace Omniplay;

use Omniplay\Exception\InvalidConfigException;

/**
 * A provider's configuration while its factory builds it: the options
 * given, the factory's defaults under them, and the keys that must be
 * filled - omnisense/contracts' Config, with omniplay's prefix
 * ("omniplay.factory_name", "omniplay.backoff"...) and its
 * InvalidConfigException. Every provider takes two options besides its own:
 * "policy" (the site's tightening of its usage policy) and "usage" (what
 * the site says of itself: commercial, open_source, contract).
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    /** @var string */
    public const PREFIX = 'omniplay';

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException($message);
    }
}
