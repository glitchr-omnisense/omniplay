---
title: Models
order: 20
---

# Models

## Providers and contracts

`Omniplay\ProviderInterface` is one store or catalogue, configured. What it can be asked comes from
the contracts it implements, one per capability:

| Interface | Method | Returns |
|---|---|---|
| `SearchInterface` | `search(Query $query): array` | `list<Game>` whose title matches, best first, as short as the provider's search answers them |
| `CatalogueInterface` | `game(GameRef $ref, ?string $language = null, ?string $country = null): ?Game` | the game's whole record, `null` when unknown; `NotSupportedException` when the provider does not find a game by that identifier |
| `LibraryInterface` | `library(string $player): array` | `list<OwnedGame>`, as far as the store lets them be read (a private Steam profile reads as an empty library) |
| `OwnershipInterface` | `owns(string $player, GameRef $game): bool` | whether the player owns the game (bought it, was given a key), as the store answers it |

`ProviderInterface` itself: `getName(): string` (the factory's name, also the `source` of every
`Game`), `getTitle(): string` (`Steam`, `itch.io`), `capabilities(): Capabilities`.

`$language` is the language of the texts where the store translates (`fr`, `fr-FR`, Steam's
`french`); `$country` the country whose price is answered (ISO 3166-1 alpha-2); `null` takes the
provider's options. What a player is depends on the store: `Capabilities::$player` says it.

## Capabilities

`Omniplay\Capabilities` (a readonly value):

| Property | Type | Default | |
|---|---|---|---|
| `search` | `bool` | `false` | it implements `SearchInterface` |
| `catalogue` | `bool` | `false` | it implements `CatalogueInterface` |
| `library` | `bool` | `false` | it can list a player's games - with Steam and itch.io, only when the `key` option is set |
| `ownership` | `bool` | `false` | it can say whether a player owns a game - likewise |
| `lookupBy` | `list<Scheme>` | `[]` | the identifiers `game()` finds a game by |
| `prices` | `bool` | `false` | its records carry a price |
| `player` | `?string` | `null` | what a player is to it, for library and ownership |
| `attribution` | `?string` | `null` | what its terms ask to be shown beside its records |
| `terms` | `list<string>` | `[]` | URLs of its terms |
| `keyless` | `bool` | `false` | its catalogue needs no account nor key |
| `hosting` | `bool` | `false` | it starts and stops game servers (`HostingInterface`) |
| `services` | `list<string>` | `[]` | the services it starts a server of; any, when empty |
| `regions` | `list<string>` | `[]` | the regions its servers run in; any asked, when empty |
| `where` | `?string` | `null` | where it runs them or whom it asks, for a person to read |
| `identity` | `bool` | `false` | it verifies players' proofs from a platform (`IdentityInterface`) - Steam's only with `appid` |

| Method | |
|---|---|
| `resolves(GameRef $ref): bool` | `catalogue` is true and the ref's scheme is in `lookupBy` |
| `starts(string $service, ?string $region): bool` | `hosting` is true, the service and the region among those it runs (any, when empty) |
| `contracts(): list<string>` | `search`, `catalogue`, `library`, `ownership`, `hosting`, `identity`, those it has |
| `toArray(): array` | the same as plain values: `contracts`, `lookupBy`, `prices`, `player`, `attribution`, `terms`, `keyless`, `services`, `regions`, `where` |

| Provider | `lookupBy` | `player` | `attribution` |
|---|---|---|---|
| steam | steam | `a 64-bit Steam ID ("76561197960287930")` | `Data from Steam; Valve, Steam and their logos are trademarks of Valve Corporation` |
| gog | gog | - | `Data from GOG.com` |
| itch | itch | `an itch.io user id ("12345"), a verified e-mail address, or a download key; "me" for the library of the key's account` | `Data from itch.io` |
| igdb | igdb, steam, gog | - | `Data from IGDB.com` |
| rawg | rawg | - | `Data from RAWG (rawg.io), with a link on every page that shows it` |

`Registry::library()` and `::ownership()` give a provider only when its `capabilities()` says it
can (`library`, `ownership` true), else a `NotSupportedException`: Steam's and itch.io's providers
implement both interfaces, and without a `key` they are refused there.

## Factory and Config

`Omniplay\ProviderFactoryInterface` builds a provider from options: `getName(): string` and
`create(array $options = []): ProviderInterface`. Every provider's factory extends
`Omniplay\ProviderFactory`: `createConfig($options)` fills a `Config` (an `ArrayObject`) with the
options given, then the factory's defaults under them; the keys of `omniplay.required_options` are
checked (one missing, `''` or `[]` is an `InvalidConfigException` listing them all: `The "igdb"
provider needs: client_id, client_secret.`); `build()` returns the provider. Reserved keys:
`omniplay.factory_name`, `omniplay.factory_title`, `omniplay.required_options`. `Config` reads
options with types: `get()`, `string()` (`null` when empty), `int()`, `float()`, `bool()` (`"1"`,
`"true"`, `"yes"`, `"on"`).

Every factory takes an optional `HttpClientInterface`; given none, it makes its own with a
20-second timeout. Building calls nothing (IGDB's Twitch token is asked on the first question).

## Registry

`Omniplay\Registry` holds the application's providers by name:

```php
$stores = new Registry(
    [new SteamFactory($http), new IgdbFactory($http)],      // iterable of factories, keyed by getName()
    [
        'steam' => ['factory' => 'steam', 'options' => ['key' => '...']],
        'igdb' => ['factory' => 'igdb', 'options' => ['client_id' => '...', 'client_secret' => '...']],
    ],
);
```

| Method | |
|---|---|
| `get(string $name): ProviderInterface` | the provider, built on first use, then kept |
| `search($name)`, `catalogue($name)`, `library($name)`, `ownership($name)` | the same provider as that contract, or `NotSupportedException` (`The "i" provider (Omniplay\Itch\ItchProvider) does not search.`) |
| `game(GameRef $ref, ?string $language = null, ?string $country = null, string ...$names): ?Game` | the record from the first provider - in the configuration's order, or in the order of `$names` - that implements `CatalogueInterface`, resolves the ref and finds the game; `null` when none does |
| `has($name)`, `options($name)`, `names()`, `factories()` | the configuration |

`game()` skips the providers that cannot resolve the ref without calling them and goes on when one
answers `null`; an exception of a provider ends it. A name not configured, or a factory not
installed, is an `InvalidConfigException` (`No "x" game provider; configured: steam.`).

## GameRef and Scheme

`Omniplay\Model\GameRef` (readonly, `\Stringable`): `new GameRef(Scheme $scheme, string|int $id)`
(trimmed; empty: `InvalidArgumentException`), and `GameRef::steam(620)`, `GameRef::gog(1207658924)`,
`GameRef::itch('leafo/x-moon')`, `GameRef::igdb(72)`, `GameRef::rawg(4200)` (or a slug,
`GameRef::rawg('portal-2')`). `(string) $ref` is `steam:620`. There is no `GameRef::epic()`: no
provider resolves an Epic identifier.

`Omniplay\Model\Scheme`, a string-backed enum:

| Case | Value | Identifier |
|---|---|---|
| `Steam` | `steam` | the app id: `620` |
| `Gog` | `gog` | the product id: `1207658924` |
| `Itch` | `itch` | the game's page, `creator/game`: `leafo/x-moon` for https://leafo.itch.io/x-moon (its number, `3`, is what its API answers) |
| `Epic` | `epic` | the Epic Games Store product slug, as IGDB and RAWG give it |
| `Igdb` | `igdb` | a number |
| `Rawg` | `rawg` | a number, or its slug where RAWG takes it |

## Identifiers

`Omniplay\Model\Identifiers` (readonly): one game's identifiers keyed by scheme value - what lets a
store page be found from a catalogue's record. `Identifiers::of(['steam' => 620, 'igdb' => 72])`
(values trimmed, empty ones dropped, keys sorted; a key that is not lower-case letters, digits, `-`
or `_` is an `InvalidArgumentException`), `none()`, `get()`, `has()`, `with()`, `merge()` (these,
completed by the other's), `isEmpty()`, `toArray()`.

## Game

`Omniplay\Model\Game` (readonly) is a game as one store or catalogue knows it. What the provider
does not give is empty or `null`, never guessed: a search answer is usually shorter than a record.

| Property | Type | |
|---|---|---|
| `title` | `string` | |
| `identifiers` | `Identifiers` | its identifiers in every store the provider knows |
| `source` | `string` | the provider's name: `steam`, `igdb`... |
| `url` | `?string` | the game's page at the store or catalogue |
| `summary` | `?string` | plain text (HTML stripped where the store gives HTML) |
| `released` | `?\DateTimeImmutable` | the day of release (UTC midnight) |
| `comingSoon` | `bool` | not released yet, as the store says (IGDB: a release date in the future) |
| `developers`, `publishers` | `list<string>` | |
| `genres`, `tags` | `list<string>` | the provider's words, in the language asked where it translates (Steam's categories and IGDB's themes are its tags) |
| `platforms` | `list<Platform>` | |
| `price` | `?Price` | in the country asked, where the store has prices |
| `images` | `list<Image>` | |
| `ratings` | `list<Rating>` | |

| Method | |
|---|---|
| `ref(Scheme $scheme): ?GameRef` | the game named in another store, when the record knows its identifier there |
| `image(ImageKind $kind = Cover): ?Image` | the first picture of that kind |
| `runsOn(Platform $platform): bool` | |
| `toArray(): array` | every field as plain values (`released` as `Y-m-d`) |

## Platform

`Omniplay\Model\Platform`: `Windows`, `Mac`, `Linux`, `Android`, `Ios`, `Web`, `Switch`,
`PlayStation`, `Xbox` - families a store's finer names are folded into.

`Platform::fromLabel(string $label): ?Platform` reads a label case-insensitively, the first rule
that matches winning:

| Platform | A label that |
|---|---|
| `Windows` | contains `windows`, or is `pc` or `win` |
| `Mac` | contains `mac` or `osx`, or is `os x` |
| `Linux` | contains `linux` or `steamos` |
| `Android` | contains `android` |
| `Ios` | is `ios`, or contains `iphone` or `ipad` |
| `Web` | contains `web`, `html` or `browser` |
| `Switch` | contains `switch` |
| `PlayStation` | contains `playstation`, or starts with `ps` and a digit |
| `Xbox` | contains `xbox` |

Anything else is `null` (left out). `fromLabels(iterable $labels): list<Platform>` gives each
family once, in the order first met.

## Price

`Omniplay\Model\Price` (readonly): `amount` (minor units: `969` EUR is 9.69 €), `currency` (ISO
4217; `XXX` for a free Steam game whose store names no currency), `initial` (the price before a
discount, when there is one), `discount` (percent, `0` when none); `isFree()` is `amount === 0`.

## Image, ImageKind

`Omniplay\Model\Image` (readonly): `kind`, `url` (where the store serves it, under the store's terms
and the publisher's rights), `width`, `height`. `Omniplay\Model\ImageKind`: `Cover` (box art,
portrait), `Header` (the store's wide banner: Steam's header, a capsule), `Screenshot`,
`Background`, `Logo`, `Icon`.

## Rating

`Omniplay\Model\Rating` (readonly): `source` (`metacritic`, `igdb`, `rawg`, `gog`), `value`,
`scale` (100 by default; 5 for GOG and RAWG), `votes`.

## OwnedGame

`Omniplay\Model\OwnedGame` (readonly): `identifiers`, `title`, `playtime` (minutes, `?int`),
`lastPlayed` (`?\DateTimeImmutable`); `toArray()` gives `lastPlayed` in ISO 8601.

## Query

`new Query(string $text, ?string $language = null, ?string $country = null, int $limit = 10)`: the
words (trimmed; empty: `InvalidArgumentException` `A search needs words.`), the language of the
texts answered, the country of the prices, at most `limit` games (1 to 50, `A search answers 1 to
50 games.` otherwise).

## The HTTP layer

`Omniplay\Http\JsonClient` and `Omniplay\Http\Data` are the same as Omnifilm's: headers on every
request, a 404 read as `null`, any other status of 400 or more a `ProviderException` naming the
method and the path (the body's text after it, tags stripped, 300 characters at most), a body
that is not JSON a `ProviderException`, a network error an `UnreachableException`; `Data` reads an
answer by key or dotted path without trusting its shape (`string()`, `int()`, `float()`, `bool()`,
`date()` - a day in UTC, or a format given -, `data()`, `list()`, `items()`, `strings($key,
$field)`, `keys()`, `toArray()`).

## Online play: models and contracts

### Contracts

| Contract | Implemented by | What it answers |
|---|---|---|
| `FleetInterface` | `Fleet` (over a `StoreInterface`) | `register()`, `heartbeat()` (null when unknown), `get()`, `servers()` of a service, `healthy()` ones, `available()` ones (a seat left; healthy within the ttl or starting within the startup's wait), `occupy()` (one more player, null when full, draining or down), `retireStale()` (the removed servers), `remove()` |
| `SeatingInterface` | `Seating` | `seat(Service, SeatRequest): ?Seat` |
| `TicketsInterface` | `Tickets` (over a `SecretsInterface`) | `mint(Service, room, claims): Ticket`, `verify(Service, token, room): ?Ticket` |
| `HostingInterface` | providers (`omniplay/docker`, `omniplay/edgegap`, `Testing\FixedHosting`) | `start(Service, secret, region, tags): Server` (Starting, not registered), `stop(Server)`, `running(service)` |
| `IdentityInterface` | providers (`omniplay/steam`, `Testing\FixedIdentity`); optional | `platform()`, `verify(proof): Identity` (or an `IdentityRefusedException` saying why): [Player identity](identity.md) |
| `ProviderInterface` | every provider | `getName()`, `getTitle()`, `capabilities()` |
| `Storage\StoreInterface` | `InMemoryStore`, the application's own | `save()`, `find()`, `all()`, `delete()` |
| `Secret\SecretsInterface` | `EnvironmentSecrets`, `FixedSecrets`, the application's own (a vault) | `secret(reference): string` |

`Heartbeats` is no contract: it is the application's end of a server's heartbeat
([the heartbeat](heartbeat.md)).

### Capabilities

The same `Capabilities` as the stores' ([above](#capabilities)), its online-play fields:
`Capabilities(hosting, services, regions, where, identity)`: whether the provider starts servers,
of which services (any when empty), in which regions (any when empty), where it runs them for a
person to read, and whether it verifies players' proofs from a platform. `starts($service,
$region)` is what `Seating` and `Registry::hostingFor()` ask; `contracts()` lists `hosting` and
`identity` as they hold.

### The registry

`new Registry($factories, $config)`: `$config` maps a name to `['factory' => ..., 'options' =>
[...]]`. `get()`, `has()`, `hosting($name)` (a `NotSupportedException` when the provider starts
nothing), `hostingFor($service, $region, ...$names)` (the first that starts it there, or null),
`identity($name)` (a `NotSupportedException` when the provider verifies no player),
`identityFor($platform, ...$names)` (the first that verifies that platform's players, or null),
`options()`, `names()`, `factories()`.

### Server

| Field | |
|---|---|
| `id` | its name, unique in the fleet; the room its tickets name |
| `service` | the `Service`'s name |
| `address` | where players reach it, as their client takes it (`203.0.113.7:7777`, `wss://eu1.example.org/play`) |
| `capacity`, `players` | seats, and players held (`free()` the difference) |
| `health` | `Health::Starting`, `Healthy`, `Draining`, `Down` (`seats()`: starting or healthy) |
| `heartbeat` | when it was last heard from (`silence($now)` in seconds) |
| `region`, `tags` | what a seat may ask of it (`has(...$tags)`) |
| `engine` | declared by the application, never read |
| `provider`, `instance` | the provider that started it and its handle there (a container's id) |

`toArray()` and `fromArray()` are what a store keeps; `withHeartbeat()` and `withPlayers()` give
a new one.

### Service

`new Service($name, $url = null, $secret = 'OMNIPLAY_TICKET_SECRET', $ttl = 60, $capacity = 16,
$engine = null)`; `fromArray($name, $values)` reads a configuration's entry. `$secret` is a
reference, read through a `SecretsInterface`; `$url` is the public address where tickets are
presented when the service has one of its own (a relay).

### Seat, Ticket, SeatRequest, HeartbeatResult

- `SeatRequest($player, $region = null, $tags = [], $claims = [])`: `$player` a string, or an
  `Identity` a platform verified (then the player's id is `Identity::player()` and `$identity`
  keeps it).
- `Identity($platform, $id, $claims)`: `player()` is `<platform>:<id>`.
- `Seat($player, Server, Ticket, $started)`: `toArray()` is what the client is handed (address,
  ticket, expiry in Unix seconds, server, service, region, engine, started).
- `Ticket($token, $room, $expires, $claims)`: the claims without `room` and `exp`.
- `HeartbeatResult($status, $reason, ?Server)`: `accepted()`, `toArray()` the response's body.

### The engine

`Service::$engine` and `Server::$engine` are free strings the application declares:
`unity-mirror`, `unity-netcode`, `godot`, `node`, `custom`, or anything else. Omniplay stores them,
returns them (in `Seat::toArray()`, a Docker server's `OMNIPLAY_ENGINE`), and never branches on
them: it does not detect what a server runs.
