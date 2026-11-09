<?php

namespace Omniplay\Exception;

use Omniplay\Model\GameRef;

/** A provider asked for what it does not do: a library from a catalogue, a game by a store it does not know, a server of a service it does not know, a region it does not run in. */
final class NotSupportedException extends \LogicException implements OmniplayException
{
    public static function capability(object $provider, string $name, string $capability): self
    {
        return new self(\sprintf('The "%s" provider (%s) does not %s.', $name, $provider::class, $capability));
    }

    public static function ref(string $provider, GameRef $ref): self
    {
        return new self(\sprintf('%s does not find a game by its %s identifier.', $provider, $ref->scheme->value));
    }

    public static function service(string $provider, string $service, ?string $region = null): self
    {
        return new self(\sprintf('%s starts no server of the "%s" service%s.', $provider, $service, null !== $region ? \sprintf(' in the "%s" region', $region) : ''));
    }
}
