---
title: Store records and prices
order: 32
---

# Store records and prices

`CatalogueInterface::game(GameRef $ref, ?string $language = null, ?string $country = null): ?Game`
gives a game's whole record by one of its identifiers. Every provider answers it, each for the
schemes of its `capabilities()->lookupBy`; a ref of another scheme is a `NotSupportedException`
before any call, an unknown game `null`.

```php
use Omniplay\Model\GameRef;
use Omniplay\Model\ImageKind;

$portal = $stores->catalogue('steam')->game(GameRef::steam(620), 'fr', 'FR');

$portal->summary;                                    // plain text, in French
$portal->price?->amount;                             // minor units, in France's currency
$portal->image(ImageKind::Header)?->url;             // served from Steam's servers, under its terms
```

The record Steam's provider reads from the answer its tests recorded on 2026-10-24 (in French, for
France), printed in the [harness](harness.md) on 2026-10-27 - each field of the `Game`, then the
call:

```
Portal 2 | 2011-04-18 | 969 EUR initial - -0%
  ids {"steam":"620"} platforms ["windows","linux"]
  dev ["Valve"] pub ["Valve"] genres ["Action","Aventure"] tags ["Solo","Multijoueur","Coopération"]
  summary L'« initiative de tests perpétuels » a été étendue pour vous permettre
  header https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/faffc0f560786e2f05104a8d2fac837c6969bf13/header.jpg?t=1790187113
  background https://store.akamai.steamstatic.com/images/storepagebackground/app/620?t=1790187113
  screenshot https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/620/ss_f3f6787d74739d3b2ec8a484b5c994b3d31ef325.1920x1080.jpg?t=1790187113
  rating metacritic 95/100
  https://store.steampowered.com/app/620/
  > GET https://store.steampowered.com/api/appdetails {"appids":"620","cc":"FR","l":"french"}
```

## From a catalogue to the stores

IGDB and RAWG know a game's identifiers on the stores; `Game::ref()` turns one into the key to a
store's record, and `Registry::game()` asks the providers in turn:

```php
use Omniplay\Model\GameRef;
use Omniplay\Model\Scheme;

$entry = $stores->catalogue('igdb')->game(GameRef::igdb(72));          // Portal 2 at IGDB
$onSteam = null !== ($ref = $entry?->ref(Scheme::Steam)) ? $stores->game($ref, 'fr', 'FR', 'steam') : null;
$onGog = null !== ($ref = $entry?->ref(Scheme::Gog)) ? $stores->game($ref, 'fr', 'FR', 'gog') : null;
```

IGDB also finds a game by its Steam or GOG number (`/v4/external_games`), so
`$stores->game(GameRef::steam(620))` with IGDB configured before Steam answers IGDB's record.

| | Cross identifiers in a record |
|---|---|
| igdb | steam, gog, epic, itch (IGDB's external game sources 1, 5, 26, 30) |
| rawg | steam, itch, epic (read from the links of `/games/{id}/stores`) |
| steam, gog, itch | their own only |

## Prices

Steam, GOG and itch.io give prices; IGDB and RAWG none.

| | Country | Price |
|---|---|---|
| steam | `cc` = the country asked, else `country` | `price_overview`: `final`, `initial`, `discount_percent`; a free game is `0` in its currency, or `XXX` |
| gog | `/products/{id}/prices?countryCode=...` (a second call) | the country's own currency, `finalPrice` and `basePrice`, the discount computed |
| itch | none: the page's price as shown | `price` and `original_price` (`$2.00`, `€4,99`, `$1,000.00`, `¥500`), the currency read from the symbol (`$` USD, `€` EUR, `£` GBP, `¥` JPY; USD when none), in minor units (none for JPY), thousands separators read as such |

A price is the store's at the moment it is read: sales change it. Cache it for hours, not days,
and show where it comes from.

## What each provider reads

| | Calls | Language | Notable fields |
|---|---|---|---|
| steam | `appdetails?appids=...&cc=...&l=...` | Steam's names (`fr` -> `french`, 17 mapped) | short description (HTML stripped), release date read in the store's languages (`18 avr. 2011`), coming soon, developers, publishers, genres, categories as tags, platforms, price, header, background, screenshots, Metacritic |
| gog | `/products/{id}?expand=description&locale=...`, then its prices | a locale (`fr` -> `fr-FR`) | description's lead (else the full one), release date, in development, systems, logo, background, icon; no studios nor genres (the product API gives none) |
| itch | `https://{creator}.itch.io/{game}/data.json` | - | title, authors as developers, tags, price, cover, screenshots |
| igdb | Twitch token; `external_games` for a Steam or GOG number; `/v4/games` | - | summary, first release date, involved companies (developers, publishers), genres, themes as tags, platforms, cover and screenshots (`images.igdb.com`), total rating out of 100 with its count, the cross identifiers |
| rawg | `/api/games/{id}`, then `/api/games/{id}/stores` | - | raw description, release date, `tba` as coming soon, developers, publishers, genres, 20 tags, platforms and parent platforms, background and screenshots, RAWG rating out of 5, Metacritic, the cross identifiers |

Each provider's manual gives the fields in detail.
