<?php

namespace Omniplay\Model;

/** A game in a player's library: which, and how long it was played. */
final readonly class OwnedGame
{
    /** @param ?int $playtime minutes */
    public function __construct(public Identifiers $identifiers, public string $title, public ?int $playtime = null, public ?\DateTimeImmutable $lastPlayed = null)
    {
    }

    /** @return array{identifiers: array<string, string>, title: string, playtime: ?int, lastPlayed: ?string} */
    public function toArray(): array
    {
        return ['identifiers' => $this->identifiers->toArray(), 'title' => $this->title, 'playtime' => $this->playtime, 'lastPlayed' => $this->lastPlayed?->format(\DATE_ATOM)];
    }
}
