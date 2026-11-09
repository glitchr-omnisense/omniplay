<?php

namespace Omniplay\Model;

/** A score given somewhere: Metacritic 95/100, IGDB 91/100 from 1,200 ratings. */
final readonly class Rating
{
    public function __construct(public string $source, public float $value, public float $scale = 100.0, public ?int $votes = null)
    {
    }

    /** @return array{source: string, value: float, scale: float, votes: ?int} */
    public function toArray(): array
    {
        return ['source' => $this->source, 'value' => $this->value, 'scale' => $this->scale, 'votes' => $this->votes];
    }
}
