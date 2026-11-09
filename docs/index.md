# omnisense/omniplay

Omniplay is one PHP contract for games, on two sides that share one registry:

- **On the stores**: a game's store record (texts, release date, studios, platforms, price in a
  country, pictures, scores, its identifiers on other stores), a search by title, a player's
  library and whether a player owns a game. Everything is read: nothing is bought, granted or
  written. An application asks every store or catalogue the same questions and gets the same
  `Game` back.
- **Online play**, on the platform side: which game servers are up (the fleet, kept by their
  heartbeats), which one a player goes on (seating), the short-lived ticket that lets them in, the
  hosting providers that start and stop servers, and a player's identity verified by their
  platform. It knows no engine and no netcode: a server is an address, a capacity, a count of
  players and a health; what it runs is a string the application declares (`engine`).

Each store, host or platform is a package of its own (`omniplay/steam`, `omniplay/docker`...) that
implements the contracts it can fulfil; the packages that run inside the game server
(`omniplay/dotnet`, `/mirror`, `/netcode`, `/godot`, `/node`) verify the tickets and send the
heartbeats.

```php
$stores->search('gog')->search(new Query('witcher', 'fr', 'FR'));                   // Game[], best first
$stores->catalogue('steam')->game(GameRef::steam(620), 'fr', 'FR');                 // ?Game, with its French price
$stores->game(GameRef::steam(620));                                                 // the first provider that finds it
$stores->library('steam')->library('76561197960287930');                            // OwnedGame[]: playtime, last played
$stores->ownership('itch')->owns('12345', GameRef::itch('leafo/x-moon'));           // bool

$seat = $seating->seat($game, new SeatRequest('player-42', 'eu', ['ranked']));      // ?Seat: address, ticket, expiry
$fleet->heartbeat('eu-1', 5);                                                       // a server says it holds 5 players
$fleet->retireStale();                                                              // the servers fallen silent, removed
$heartbeats->receive($request->headers->get('Authorization', ''), $request->toArray());   // a server's own heartbeat
$stores->identityFor('steam')?->verify($steamTicket);                               // Identity "steam:7656...", or refused
```

The design is the one of the other Omnisense families: a provider's **factory** fills a
configuration with its name, its required options and the defaults of the others, then builds the
**provider**; a **registry** holds the application's providers by name. A provider says what it can
be asked in its **capabilities** - which contracts it answers (search, catalogue, library,
ownership, hosting, identity), the identifiers it finds a game by, what a player is to it, what its
terms ask to be shown, the services and regions it starts servers of - and an identifier it cannot
resolve is refused before any call. The core needs no framework: it requires
`symfony/http-client-contracts` and nothing else.

Published as `omnisense/omniplay`. The namespace is `Omniplay\`; providers keep the `omniplay/`
vendor and the `Omniplay\<Provider>\` namespace. The stores' side was the omnigame family
(`omnisense/omnigame`, `omnigame/*`), merged into omniplay on 2026-11-09: `Omnigame\` is now
`Omniplay\`, `omnigame/<provider>` is `omniplay/<provider>`.

## Online play: the flow

1. A server starts - by hand, or through a hosting provider (`HostingInterface::start()`), which
   hands it its ticket secret in its environment - and is registered in the fleet.
2. It sends heartbeats (players, health) to the application's route, which hands them to
   `Heartbeats::receive()`; a server the fleet does not know registers itself with its address.
3. A player asks the application to play - named by the application, or verified by their
   platform (`IdentityInterface`). `Seating::seat()` finds the fullest server of the service that
   still has a seat (a ready one first, then one starting), or starts one, counts the player on
   it, and mints a ticket whose room is the server's id.
4. The client connects to the address and presents the ticket; the server verifies it with the
   secret (signature, room, expiry) and lets the player in or not. It never calls the application.
5. `Fleet::retireStale()` removes the servers fallen silent; the application stops those a
   provider started.

## When to use it

- A studio's or a blog's page that shows a game with its price on Steam and GOG, kept current.
- A catalogue page fed by IGDB or RAWG, which also give a game's Steam, GOG, itch.io or Epic
  identifiers, leading to the store pages (`Registry::game()` asks each provider in turn).
- A community site that shows a player's Steam library and playtime, or a studio that checks
  whether a player owns its itch.io game before granting a role or a download.

- A game whose dedicated servers run as containers on one machine or a few (omniplay/docker), or
  as Edgegap deployments near the players (omniplay/edgegap), started as players come.
- A relay or a lobby service (a WebSocket server) that must let in only the players the site
  signed in: one `Service` with its public URL, the tickets alone.
- A Steam game whose players are seated under their verified SteamID (omniplay/steam's identity).

It sells nothing and grants nothing: no purchase, no key handed out, nothing written to a store. It
does no netcode, no matchmaking by skill, no billing. It stores nothing either: keeping records and
prices (each store's terms say for how long) and the fleet's store are the application's job.

## Providers

One list: each provider is a package of its own and implements the contracts it can fulfil. Those
on the platform are called by the application; those inside the game server call the application
(heartbeats) and check the tickets it minted - omniplay never calls them. The capabilities are each
provider's `capabilities()`; library and ownership are offered only with the key they need,
identity only with the app id it needs.

| Package | Runs | Factory name | Contracts | Credentials |
|---|---|---|---|---|
| [`omniplay/steam`](https://github.com/glitchr-omnisense/omniplay-steam) | the platform | `steam` | search, catalogue by steam; library and ownership with `key` (a 64-bit Steam ID); identity with `appid` (a session ticket, `AuthenticateUserTicket`) | none for the store; a Steam Web API key for players' libraries; the publisher key for identity |
| [`omniplay/gog`](https://github.com/glitchr-omnisense/omniplay-gog) | the platform | `gog` | search, catalogue by gog, prices | none |
| [`omniplay/itch`](https://github.com/glitchr-omnisense/omniplay-itch) | the platform | `itch` | catalogue by itch page (no search API), prices; library (the key's own account) and ownership (user id, e-mail, download key) with `key` | none for pages; a server-side API key |
| [`omniplay/igdb`](https://github.com/glitchr-omnisense/omniplay-igdb) | the platform | `igdb` | search, catalogue by igdb, steam, gog | a Twitch application's client id and secret |
| [`omniplay/rawg`](https://github.com/glitchr-omnisense/omniplay-rawg) | the platform | `rawg` | search, catalogue by rawg (number or slug) | an API key |
| [`omniplay/docker`](https://github.com/glitchr-omnisense/omniplay-docker) | the platform | `docker` | hosting: servers as containers, Docker Engine API on a unix socket or TCP (TLS) | the engine's socket or certificates |
| [`omniplay/edgegap`](https://github.com/glitchr-omnisense/omniplay-edgegap) | the platform | `edgegap` | hosting: servers as Edgegap deployments near the players | an Edgegap API token (by reference) |
| [`omniplay/dotnet`](https://github.com/glitchr-omnisense/omniplay-dotnet) (NuGet `Omniplay.Dotnet`, UPM `io.omniplay.dotnet`) | inside the game server: any .NET (a .NET server, Unity, Godot's C#) | - | tickets (verify, mint), heartbeat | the ticket secret, from the server's environment |
| [`omniplay/mirror`](https://github.com/glitchr-omnisense/omniplay-mirror) (UPM `io.omniplay.mirror`) | inside a Unity server with Mirror | - | tickets (a `NetworkAuthenticator`), heartbeat, over omniplay/dotnet | idem |
| [`omniplay/netcode`](https://github.com/glitchr-omnisense/omniplay-netcode) (UPM `io.omniplay.netcode`) | inside a Unity server with Netcode for GameObjects | - | tickets (the connection approval), heartbeat, over omniplay/dotnet | idem |
| [`omniplay/godot`](https://github.com/glitchr-omnisense/omniplay-godot) (addon `omniplay_godot`) | inside a Godot 4 server, GDScript | - | tickets, heartbeat | idem |
| [`omniplay/node`](https://github.com/glitchr-omnisense/omniplay-node) (npm `@omniplay/node`) | inside a Node.js server | - | tickets (verify, mint), heartbeat | idem |

The fleet, seating, the tickets' format and the heartbeats belong to no provider: they are the
core's (`Fleet`, `Seating`, `Tickets`, `Heartbeats`). Each provider that reads tickets is checked
against `Tests/vectors.json`, of which it holds a byte-identical copy; a server written in anything
else follows [Tickets](tickets.md) and [the heartbeat](heartbeat.md).

Cross identifiers: IGDB gives a game's Steam, GOG, Epic and itch.io identifiers; RAWG its Steam,
itch.io and Epic ones (read from the links of the stores that sell it). The core's test provider,
`Omniplay\Testing\FixedProvider`, answers the four store contracts; `FixedHosting` and
`FixedIdentity` start nothing and vouch for a fixed list of players ([Testing](testing.md)).

**Epic Games Store: not written.** It publishes no catalogue API; a player's entitlements are read
through Epic Online Services by the game's own product, with that product's credentials and the
player's sign-in. IGDB and RAWG give a game's Epic identifier meanwhile (`Scheme::Epic`), which no
provider resolves.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP, the stores' APIs.
- [Symfony](symfony.md) and [Laravel](laravel.md): wiring the registry in a framework (no bridge
  yet: a service definition of the application's).
- [Models](models.md): providers, capabilities, factories, the registry, every model and enum.
- Usage: [search](usage-search.md), [store records and prices](usage-catalogue.md),
  [players: libraries and ownership](usage-players.md), [seating](usage-seating.md) (how a server
  is chosen, started, and a ticket minted).
- [Tickets](tickets.md): the format, field by field, with its vectors; [the heartbeat](heartbeat.md):
  the protocol between a server and the application.
- [Player identity](identity.md): a player verified by a platform, seated under that id.
- [Configuration reference](configuration.md): the registry's configuration and every provider's
  options.
- [Errors](errors.md): every exception and when it is thrown.
- [Testing](testing.md): the fixed providers, the recorded HTTP client, the bare test case.
- [The Docker harness](harness.md).
- [Writing a provider](providers.md), and the providers' terms.
- [Verified, and not](verified.md).
