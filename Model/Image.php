<?php

namespace Omniplay\Model;

/** A picture of a game, where its store serves it, under the store's terms and the publisher's rights. */
final readonly class Image
{
    public function __construct(public ImageKind $kind, public string $url, public ?int $width = null, public ?int $height = null)
    {
    }

    /** @return array{kind: string, url: string, width: ?int, height: ?int} */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'url' => $this->url, 'width' => $this->width, 'height' => $this->height];
    }
}
