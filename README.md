# omnisense/omniplay

Games under any PHP host, behind contracts by capability, on two sides that share one registry:

- **On the stores**: a game's store record (texts, dates, studios, platforms, price in a country,
  pictures, scores, its identifiers on other stores), a search by title, a player's library and
  whether a player owns a game. Read only: nothing is bought, granted or written.
- **Online play**, on the platform side and whatever engine or netcode the game uses: the
  application keeps a fleet of game servers (registered, kept by their heartbeats, retired when
  they fall silent), seats a player on a fitting server - or has a hosting provider start one -
  and hands the player a short-lived signed ticket and the server's address. The server checks the
  ticket by itself, with the secret it shares with the application, never calling the application
  back. A player may be one their platform verified (a Steam session ticket).

One set of models and contracts; each store, host or platform is a package of its own, implementing
the contracts it can fulfil.

```php
use Omniplay\{Fleet, Registry, Seating, Tickets};
use Omniplay\Docker\DockerFactory;
use Omniplay\Gog\GogFactory;
use Omniplay\Model\{GameRef, Query, SeatRequest, Service};
use Omniplay\Secret\EnvironmentSecrets;
use Omniplay\Steam\SteamFactory;
use Omniplay\Storage\InMemoryStore;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
$secrets = new EnvironmentSecrets();
$providers = new Registry([new SteamFactory($http, $secrets), new GogFactory($http), new DockerFactory()], [
    'steam' => ['factory' => 'steam', 'options' => ['key' => getenv('STEAM_WEB_API_KEY'), 'appid' => 480]],
    'gog' => ['factory' => 'gog'],
    'local' => ['factory' => 'docker', 'options' => ['public_host' => 'play.example.org', 'services' => ['game' => ['image' => 'example/game-server:1.4', 'port' => '7777/udp']]]],
]);

// On the stores
$portal = $providers->catalogue('steam')->game(GameRef::steam(620), 'fr', 'FR');
$found = $providers->search('gog')->search(new Query('witcher', 'fr', 'FR'));
$owns = $providers->ownership('steam')->owns('76561197960287930', GameRef::steam(620));

// Online play
$game = new Service('game', secret: 'GAME_TICKET_SECRET', ttl: 60, capacity: 16, engine: 'unity-mirror');
$seating = new Seating(new Fleet(new InMemoryStore()), new Tickets($secrets), $providers->hostingFor('game'), $secrets);
$player = $providers->identityFor('steam')?->verify($steamTicket);   // Identity "steam:7656...", or refused aloud
$seat = $seating->seat($game, new SeatRequest($player, 'local', ['ranked']));
$seat?->toArray();   // ['address' => 'play.example.org:49153', 'ticket' => 'eyJ...', 'expires' => 1800000060, ...]
```

Plain PHP: no container, no framework. The core requires only `symfony/http-client-contracts`
(interfaces); a provider that calls an API requires `symfony/http-client` and takes the
application's client.

## The shape

- **Models** (`Omniplay\Model`). The stores': `Game` (title, `Identifiers`, page, summary, release
  date, coming soon, developers, publishers, genres, tags, `Platform`s, `Price`, `Image`s,
  `Rating`s), `GameRef` (a `Scheme` - steam, gog, itch, epic, igdb, rawg - and an identifier),
  `Query`, `OwnedGame`, `Platform::fromLabels()` folding a store's names ("PC (Microsoft Windows)",
  "osx", "PlayStation 5") into families. Online play's: `Server` (id, service, address, capacity,
  players, `Health`, last heartbeat, region, tags, engine, the provider and handle that started
  it), `Service` (name, public URL, the secret's reference, the tickets' ttl, capacity, engine),
  `Seat`, `Ticket`, `SeatRequest`, `HeartbeatResult`, `Identity` (platform, id, claims). The engine
  is declared, never detected: a free string the application sets, stored and returned.
  See [docs/models.md](docs/models.md).
- **Contracts by capability**, implemented by providers: `SearchInterface`, `CatalogueInterface`,
  `LibraryInterface`, `OwnershipInterface`, `HostingInterface` (start, stop, running),
  `IdentityInterface` (a player's proof verified by a platform; required by nothing).
  `ProviderInterface::capabilities()` says which a provider has, the identifiers it finds a game
  by, what a player is to it, what its terms ask to be shown, the services and regions it starts
  servers of.
- **What belongs to no provider**, the core's own: `FleetInterface` and `Fleet` (register,
  heartbeat, healthy, available, occupy, retire the stale), `SeatingInterface` and `Seating`,
  `TicketsInterface` and `Tickets`, `Heartbeats` (the application's end of a server's heartbeat,
  signed for the room `heartbeat:<server id>`).
- **Tickets** (`Omniplay\Ticket\TicketSigner`): `base64url(json payload) "." hex(hmac_sha256(that
  base64url, secret))`, the payload naming `room` and `exp`, in omnibase's format without requiring
  omnibase. [docs/tickets.md](docs/tickets.md) is the format for a server in any language,
  `Tests/vectors.json` its vectors, [docs/heartbeat.md](docs/heartbeat.md) the heartbeat.
- **Storage** behind `Storage\StoreInterface` (`InMemoryStore` here, the application's own
  persistence otherwise); **secrets** by reference (`Secret\EnvironmentSecrets`, `FixedSecrets`):
  a secret is never a field of anything stored or returned.
- **Registry, Config, ProviderFactory** as in the other omni families, one for every contract:
  `Registry::game()` asks, in order, each provider that resolves the identifier; `hostingFor()`
  the first that starts the service there; `identityFor()` the first that verifies the platform's
  players.
- **Http\JsonClient**, **Http\Data**, **Http\Redaction**; **Testing** (`FixedProvider`,
  `FixedHosting`, `FixedIdentity`, `RecordedHttpClient`, `BareTestCase`).

## Providers

One list. Each is a package of its own, named `omniplay/<name>`, and implements the contracts it
can fulfil. Those on the platform are called by the application; those inside the game server
verify the tickets players present and send the server's heartbeats - they call the application,
never the reverse. Omniplay requires none of them; each that reads tickets holds a byte-identical
copy of `Tests/vectors.json` and is checked against it.

| Package | Runs | Contracts | Key |
|---|---|---|---|
| [omniplay/steam](https://github.com/glitchr-omnisense/omniplay-steam) | on the platform | search, catalogue (steam); library and ownership (Web API key); identity (a session ticket through `AuthenticateUserTicket`, with `appid`) | none for the store; a Web API key; the publisher key |
| [omniplay/gog](https://github.com/glitchr-omnisense/omniplay-gog) | on the platform | search, catalogue (gog) | none |
| [omniplay/itch](https://github.com/glitchr-omnisense/omniplay-itch) | on the platform | catalogue (itch page; no search API); the key's own library and ownership (API key) | none for pages |
| [omniplay/igdb](https://github.com/glitchr-omnisense/omniplay-igdb) | on the platform | search, catalogue (igdb, steam, gog) | Twitch client credentials |
| [omniplay/rawg](https://github.com/glitchr-omnisense/omniplay-rawg) | on the platform | search, catalogue (rawg) | API key |
| [omniplay/docker](https://github.com/glitchr-omnisense/omniplay-docker) | on the platform | hosting: game servers as Docker containers, through the Docker Engine API on a unix socket or TCP (TLS) | the engine's socket |
| [omniplay/edgegap](https://github.com/glitchr-omnisense/omniplay-edgegap) | on the platform | hosting: game servers as Edgegap deployments near the players, through Edgegap's REST API | API token |
| [omniplay/dotnet](https://github.com/glitchr-omnisense/omniplay-dotnet) (NuGet `Omniplay.Dotnet`, UPM `io.omniplay.dotnet`) | inside the game server: any .NET (a .NET server, Unity, Godot's C#; netstandard2.1, no Unity) | tickets (verify, mint), heartbeat | the ticket secret |
| [omniplay/mirror](https://github.com/glitchr-omnisense/omniplay-mirror) (UPM `io.omniplay.mirror`) | inside a Unity server with Mirror | tickets (a `NetworkAuthenticator`), heartbeat, over omniplay/dotnet | the ticket secret |
| [omniplay/netcode](https://github.com/glitchr-omnisense/omniplay-netcode) (UPM `io.omniplay.netcode`) | inside a Unity server with Netcode for GameObjects | tickets (the connection approval), heartbeat, over omniplay/dotnet | the ticket secret |
| [omniplay/godot](https://github.com/glitchr-omnisense/omniplay-godot) (addon `omniplay_godot`) | inside a Godot 4 server, GDScript | tickets, heartbeat | the ticket secret |
| [omniplay/node](https://github.com/glitchr-omnisense/omniplay-node) (npm `@omniplay/node`) | inside a Node.js server | tickets (verify, mint), heartbeat | the ticket secret |

Any server that implements [docs/tickets.md](docs/tickets.md) and
[docs/heartbeat.md](docs/heartbeat.md) does without them. Lobbies stay in the game client (Steam's
through the Steamworks SDK): the lobby's metadata carries the server's address and the ticket.

The stores' side was the omnigame family (`omnisense/omnigame`, `omnigame/*`), merged into omniplay
on 2026-11-09: `Omnigame\` is now `Omniplay\`, `omnigame/<provider>` is `omniplay/<provider>`
(`omnigame/steam`'s catalogue is in `omniplay/steam`, beside its identity).

**Epic Games Store: not written.** It publishes no catalogue API; a player's entitlements are read
through Epic Online Services by the game's own product, with that product's credentials and the
player's sign-in - something for the first studio with an EOS product to ask for. IGDB and RAWG
give a game's Epic identifier meanwhile (`Scheme::Epic`).

The Symfony and Laravel wiring will live in omnibridge when a site needs it.

## Documentation

The manual, in `docs/`:

- [Overview and providers](docs/index.md)
- [Installation, in plain PHP](docs/installation.md)
- [Symfony](docs/symfony.md) and [Laravel](docs/laravel.md)
- [Models](docs/models.md): contracts, capabilities, the registry, every model and enum
- Usage: [search](docs/usage-search.md), [store records and prices](docs/usage-catalogue.md), [players: libraries and ownership](docs/usage-players.md), [seating](docs/usage-seating.md)
- [Tickets: the format](docs/tickets.md) and [the heartbeat](docs/heartbeat.md)
- [Player identity](docs/identity.md)
- [Configuration reference](docs/configuration.md)
- [Errors](docs/errors.md)
- [Testing](docs/testing.md)
- [The harness](docs/harness.md)
- [Writing a provider](docs/providers.md)
- [Verified, and not](docs/verified.md)

## Verified, and not

- Verified (the harness, PHP 8.4, 2026-11-09, after the merge): 145 tests green on the core and
  every PHP provider (steam with both its contracts, gog, itch, igdb, rawg, docker, edgegap);
  PHPStan at its highest level on the core, the baseline empty; every ticket vector answered the
  same by omnibase's `Base\Service\Ticket\TicketSigner` (`OmnibaseParityTest`); `BareTest`: the
  bare script (a registry, a search, a game, a library, ownership, two players seated and their
  tickets checked, a JSON call answered, a 404, an error without its key) loads nothing but the
  package and the HTTP client contracts - omnibase's `Base\` among what may not.
- Verified inside the game server: omniplay/node (35 tests) and omniplay/dotnet (36) on the shared
  vectors, identical copies of `Tests/vectors.json`.
- Verified live: Steam's store (Portal 2's record and price, a search), GOG's catalogue, an itch.io
  page (no key, 2026-10-24); a container started, found, stopped and removed through Docker
  Desktop's socket (2026-11-08).
- Not verified live: Steam's library, ownership and identity, itch.io's ownership and library,
  IGDB, RAWG, Edgegap - no key nor account was used; their tests run on answers written after each
  API's documentation. Not verified: a game server of any engine seated end to end, the GDScript
  adapter (no Godot binary), mirror and netcode in a Unity editor.

See [docs/verified.md](docs/verified.md).

## Responsibility

`omnisense/omniplay` and its `omniplay/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the stores, hosts and platforms it reaches. Each keeps
its own terms (accounts and keys, rate limits, commercial use, trademarks, players' data), named in
the README of each provider. The MIT licence covers this code only and grants no right to any
service or to its data.

Whoever installs and runs these packages is responsible for complying with those terms, for
obtaining whatever they require, and for any use made of what they return - and for the servers they
start, the secrets they hand out and the players' data they keep. The authors publish them free of
charge, make no commercial use of them, and accept no liability for how they are used (see
[LICENSE](LICENSE)).

## Licence

MIT.
