<?php

namespace Omniplay\Model;

/**
 * A game as one store or catalogue knows it: its page, texts, dates,
 * studios, platforms, price, pictures, scores and its identifiers
 * elsewhere. What the provider does not give is empty or null, never
 * guessed: a search answer is usually shorter than a record.
 */
final readonly class Game
{
    /**
     * @param string         $source     the provider's name ("steam", "igdb"...)
     * @param ?string        $url        the game's page at the store or catalogue
     * @param bool           $comingSoon not released yet, as the store says
     * @param list<string>   $developers
     * @param list<string>   $publishers
     * @param list<string>   $genres     the provider's words
     * @param list<string>   $tags       the provider's words
     * @param list<Platform> $platforms
     * @param list<Image>    $images
     * @param list<Rating>   $ratings
     */
    public function __construct(
        public string $title,
        public Identifiers $identifiers,
        public string $source,
        public ?string $url = null,
        public ?string $summary = null,
        public ?\DateTimeImmutable $released = null,
        public bool $comingSoon = false,
        public array $developers = [],
        public array $publishers = [],
        public array $genres = [],
        public array $tags = [],
        public array $platforms = [],
        public ?Price $price = null,
        public array $images = [],
        public array $ratings = [],
    ) {
    }

    public function ref(Scheme $scheme): ?GameRef
    {
        $id = $this->identifiers->get($scheme);

        return null === $id ? null : new GameRef($scheme, $id);
    }

    public function image(ImageKind $kind = ImageKind::Cover): ?Image
    {
        foreach ($this->images as $image) {
            if ($kind === $image->kind) {
                return $image;
            }
        }

        return null;
    }

    public function runsOn(Platform $platform): bool
    {
        return \in_array($platform, $this->platforms, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'identifiers' => $this->identifiers->toArray(),
            'url' => $this->url,
            'summary' => $this->summary,
            'released' => $this->released?->format('Y-m-d'),
            'comingSoon' => $this->comingSoon,
            'developers' => $this->developers,
            'publishers' => $this->publishers,
            'genres' => $this->genres,
            'tags' => $this->tags,
            'platforms' => array_map(static fn (Platform $p) => $p->value, $this->platforms),
            'price' => $this->price?->toArray(),
            'images' => array_map(static fn (Image $i) => $i->toArray(), $this->images),
            'ratings' => array_map(static fn (Rating $r) => $r->toArray(), $this->ratings),
            'source' => $this->source,
        ];
    }
}
