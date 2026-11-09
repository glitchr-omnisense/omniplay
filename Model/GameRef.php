<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/** A game named by one identifier: GameRef::steam(620), GameRef::itch('leafo/x-moon'). */
final readonly class GameRef implements \Stringable
{
    public string $id;

    public function __construct(public Scheme $scheme, string|int $id)
    {
        $id = trim((string) $id);
        if ('' === $id) {
            throw new InvalidArgumentException('A game is named by a non-empty identifier.');
        }
        $this->id = $id;
    }

    public static function steam(string|int $id): self
    {
        return new self(Scheme::Steam, $id);
    }

    public static function gog(string|int $id): self
    {
        return new self(Scheme::Gog, $id);
    }

    public static function itch(string $page): self
    {
        return new self(Scheme::Itch, $page);
    }

    public static function igdb(string|int $id): self
    {
        return new self(Scheme::Igdb, $id);
    }

    public static function rawg(string|int $id): self
    {
        return new self(Scheme::Rawg, $id);
    }

    /** "steam:620" */
    public function __toString(): string
    {
        return $this->scheme->value.':'.$this->id;
    }
}
