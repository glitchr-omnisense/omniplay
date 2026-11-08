<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/**
 * A player as a platform verified them: the platform ("steam"), the
 * player's id there (a SteamID), and what the platform said besides (the
 * game's owner when it is lent, a ban). player() - "steam:76561197960287930"
 * - is what a SeatRequest takes as the player's id, and a ticket's "sub".
 */
final readonly class Identity implements \Stringable
{
    public string $platform;

    public string $id;

    /** @param array<string, mixed> $claims what the platform said besides the id */
    public function __construct(string $platform, string $id, public array $claims = [])
    {
        $platform = strtolower(trim($platform));
        $id = trim($id);
        if (1 !== preg_match('~^[a-z0-9][a-z0-9_.-]*$~', $platform)) {
            throw new InvalidArgumentException(\sprintf('A platform is named in lower-case letters, digits, "_", "." and "-", not "%s".', $platform));
        }
        if ('' === $id) {
            throw new InvalidArgumentException(\sprintf('A player verified by "%s" has an id.', $platform));
        }
        $this->platform = $platform;
        $this->id = $id;
    }

    /** The player's id across platforms: "<platform>:<id>". */
    public function player(): string
    {
        return $this->platform.':'.$this->id;
    }

    public function __toString(): string
    {
        return $this->player();
    }

    /** @return array{player: string, platform: string, id: string, claims: array<string, mixed>} */
    public function toArray(): array
    {
        return ['player' => $this->player(), 'platform' => $this->platform, 'id' => $this->id, 'claims' => $this->claims];
    }
}
