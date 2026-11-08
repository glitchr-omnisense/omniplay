<?php

namespace Omniplay\Testing;

use Omniplay\Capabilities;
use Omniplay\Exception\IdentityRefusedException;
use Omniplay\IdentityInterface;
use Omniplay\Model\Identity;
use Omniplay\ProviderInterface;

/**
 * A platform that vouches for the players it was given: each proof known
 * answers its player's id, any other is refused. Every proof asked is kept
 * in $asked. An application's tests.
 */
final class FixedIdentity implements ProviderInterface, IdentityInterface
{
    /** @var list<string> */
    public array $asked = [];

    /** @param array<string, string> $players the player's id by proof */
    public function __construct(private readonly array $players, private readonly string $platform = 'test', private readonly string $name = 'fixed-identity')
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTitle(): string
    {
        return 'Fixed identity';
    }

    public function capabilities(): Capabilities
    {
        return new Capabilities(where: 'nowhere: '.\count($this->players).' players, in memory', identity: true);
    }

    public function platform(): string
    {
        return strtolower($this->platform);
    }

    public function verify(string $proof): Identity
    {
        $this->asked[] = $proof;
        $id = $this->players[$proof] ?? throw IdentityRefusedException::refused($this->getTitle(), 'proof', 'not one it was given');

        return new Identity($this->platform, $id);
    }
}
