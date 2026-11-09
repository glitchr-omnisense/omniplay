---
title: Search
order: 31
---

# Search

`SearchInterface::search(Query $query): array` gives the games whose title matches, best first.
Steam, GOG, IGDB and RAWG answer it; itch.io has no search API (`Registry::search('itch')` is a
`NotSupportedException`).

```php
use Omniplay\Model\Query;
use Omniplay\Model\Scheme;

foreach ($stores->search('steam')->search(new Query('portal', 'fr', 'FR', limit: 5)) as $game) {
    printf("%s (Steam %s) %s\n", $game->title, $game->identifiers->get(Scheme::Steam),
        null === $game->price ? '' : sprintf('%.2f %s', $game->price->amount / 100, $game->price->currency));
}
```

On the answer Steam's tests recorded on 2026-10-24, in the [harness](harness.md) (2026-10-27), a
search for `portal` in France with a limit of 3 gives:

```
  Portal 2 620 969 EUR
  Portal 400 969 EUR
  Portal Knights 374040 1999 EUR
```

(title, Steam number, price in minor units)

A search answer is a short record: ask the catalogue for the whole one
([Store records and prices](usage-catalogue.md)).

## What each provider does

| | Call | Language | Country | Most answers | What a result carries |
|---|---|---|---|---|---|
| steam | `store.steampowered.com/api/storesearch/?term=...&l=...&cc=...` | sent (Steam's names: `fr` is `french`) | sent: its prices | what Steam answers, cut to `limit` | app number, name, page, platforms, price (and its discount), a small header (231 x 87), Metacritic score; apps only (not bundles) |
| gog | `catalog.gog.com/v1/catalog?query=like:...` | sent as a locale (`fr` is `fr-FR`) | sent; the currency is the `currency` option's | `limit`, sent | product number, title, page, release date, upcoming, developers, publishers, genres, tags, systems, price in `currency`, vertical and horizontal covers, GOG's rating out of 5 |
| igdb | `POST /v4/games` with `search "..."` (quotes escaped) | ignored | ignored | `limit`, sent | the whole record, as a lookup gives it |
| rawg | `GET /api/games?search=...&page_size=...` | ignored | ignored | `limit`, sent | RAWG number, name, page, release date, platforms, scores, background; no stores (their identifiers come with a lookup) |
| fixed | the titles' words | - | - | `limit` | the records given |

No match is `[]` everywhere. The words are sent as typed: what matches is the store's search
engine's business.
