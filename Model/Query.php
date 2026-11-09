<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/** A search by title: the words, and what narrows them. */
final readonly class Query
{
    public string $text;

    /**
     * @param ?string $language the language of the texts answered, where the store translates ("fr", "french")
     * @param ?string $country  the country whose prices are answered (ISO 3166-1 alpha-2), where the store has them
     * @param int     $limit    at most this many games (1 to 50)
     */
    public function __construct(string $text, public ?string $language = null, public ?string $country = null, public int $limit = 10)
    {
        $text = trim($text);
        if ('' === $text) {
            throw new InvalidArgumentException('A search needs words.');
        }
        if ($limit < 1 || $limit > 50) {
            throw new InvalidArgumentException('A search answers 1 to 50 games.');
        }
        $this->text = $text;
    }
}
