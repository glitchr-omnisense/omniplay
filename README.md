# omnisense/omniplay

Online play for games, on the platform side, under any PHP host and whatever engine or netcode the
game uses: the application keeps a fleet of game servers (registered, kept by their heartbeats,
retired when they fall silent), seats a player on a fitting server - or has a hosting provider
start one - and hands the player a short-lived signed ticket and the server's address. The server
checks the ticket by itself, with the secret it shares with the application, never calling the
application back. One set of models and contracts; each way of hosting servers is a package of its
own.

```php
use Omniplay\{Fleet, Heartbeats, Registry, Seating, Tickets};
use Omniplay\Docker\DockerFactory;
use Omniplay\Model\{SeatRequest, Service};
use Omniplay\Secret\EnvironmentSecrets;
use Omniplay\Storage\InMemoryStore;

$secrets = new EnvironmentSecrets();
$game = new Service('game', secret: 'GAME_TICKET_SECRET', ttl: 60, capacity: 16, engine: 'unity-mirror');
$hosts = new Registry([new DockerFactory()], [
    'local' => ['factory' => 'docker', 'options' => ['public_host' => 'play.example.org', 'services' => ['game' => ['image' => 'example/game-server:1.4', 'port' => '7777/udp']]]],
]);
$fleet = new Fleet(new InMemoryStore());
$seating = new Seating($fleet, new Tickets($secrets), $hosts->hostingFor('game'), $secrets);

$seat = $seating->seat($game, new SeatRequest('player-42', 'local', ['ranked']));
$seat?->toArray();   // ['address' => 'play.example.org:49153', 'ticket' => 'eyJ...', 'expires' => 1800000060, ...]
```

Plain PHP: no container, no framework. The core requires only `symfony/http-client-contracts`
(interfaces); a provider that calls an API requires `symfony/http-client` and takes the
application's client.

## The shape

- **Models** (`Omniplay\Model`): `Server` (id, service, address, capacity, players, `Health`, last
  heartbeat, region, tags, engine, the provider and handle that started it), `Service` (name,
  public URL, the secret's reference, the tickets' ttl, capacity, engine), `Seat` (a player's
  server and ticket), `Ticket`, `SeatRequest`, `HeartbeatResult`. The engine is declared, never
  detected: a free string the application sets ("unity-mirror", "unity-netcode", "godot", "node",
  "custom"), stored and returned; nothing branches on it.
- **Contracts by capability**: `FleetInterface` (register, heartbeat, healthy, available, occupy,
  retire the stale), `SeatingInterface`, `TicketsInterface`, `HostingInterface` (start, stop,
  running: implemented by providers), `IdentityInterface` (a player's proof verified by a
  platform: implemented by providers, required by nothing). `Fleet`, `Seating` and `Tickets` are
  the core's implementations; `Heartbeats` is the application's end of a server's heartbeat.
- **Player identity** (optional): a `SeatRequest` names its player by a string, or by the
  `Model\Identity` a platform verified (`steam:76561197960287930`, the ticket's `sub`);
  [docs/identity.md](docs/identity.md).
- **Tickets** (`Omniplay\Ticket\TicketSigner`): `base64url(json payload) "." hex(hmac_sha256(that
  base64url, secret))`, the payload naming `room` and `exp`. [docs/tickets.md](docs/tickets.md)
  is the format for a server in any language, `Tests/vectors.json` its vectors.
- **Storage** behind `Storage\StoreInterface`: `InMemoryStore` here, the application's own
  persistence otherwise. **Secrets** by reference (`Secret\EnvironmentSecrets`, `FixedSecrets`):
  a secret is never a field of anything stored or returned.
- **Registry, Config, ProviderFactory** as in the other omni families; `Http\JsonClient`,
  `Http\Data`, `Http\Redaction` as in omnigame; **Testing** (`FixedHosting`,
  `FixedIdentity`, `RecordedHttpClient`, `BareTestCase`).

## Providers

Each a package of its own, named `omniplay/<name>` as in the other families. Some run on the
platform and are called by the application; the others run inside the game server, verify the
tickets players present and send the server's heartbeats - they call omniplay, never the reverse.
Omniplay requires none of them; each that reads tickets is checked against `Tests/vectors.json`.

| Package | Runs | What it does |
|---|---|---|
| [omniplay/docker](https://github.com/glitchr-omnisense/omniplay-docker) (Composer) | on the platform | starts and stops game servers as Docker containers (`HostingInterface`), through the Docker Engine API on a unix socket or TCP (TLS) |
| [omniplay/edgegap](https://github.com/glitchr-omnisense/omniplay-edgegap) (Composer) | on the platform | starts and stops game servers as Edgegap deployments near the players (`HostingInterface`), through Edgegap's REST API |
| [omniplay/steam](https://github.com/glitchr-omnisense/omniplay-steam) (Composer) | on the platform | verifies a player's Steam session ticket and answers their SteamID (`IdentityInterface`), through the Steamworks Web API; lobbies stay in the game |
| [omniplay/dotnet](https://github.com/glitchr-omnisense/omniplay-dotnet) (NuGet `Omniplay.Dotnet`, UPM `io.omniplay.dotnet`) | inside the game server: any .NET (a .NET server, Unity, Godot's C#; netstandard2.1, no Unity) | verifies and mints tickets, sends heartbeats |
| [omniplay/mirror](https://github.com/glitchr-omnisense/omniplay-mirror) (UPM `io.omniplay.mirror`) | inside a Unity server with Mirror | a `NetworkAuthenticator` checking the ticket at connection, the heartbeat (over omniplay/dotnet) |
| [omniplay/netcode](https://github.com/glitchr-omnisense/omniplay-netcode) (UPM `io.omniplay.netcode`) | inside a Unity server with Netcode for GameObjects | the connection approval checking the ticket, the heartbeat (over omniplay/dotnet) |
| [omniplay/godot](https://github.com/glitchr-omnisense/omniplay-godot) (addon `omniplay_godot`) | inside a Godot 4 server, GDScript | verifies tickets, sends heartbeats |
| [omniplay/node](https://github.com/glitchr-omnisense/omniplay-node) (npm `@omniplay/node`) | inside a Node.js server | verifies and mints tickets, sends heartbeats |

Any server that implements [docs/tickets.md](docs/tickets.md) and [docs/heartbeat.md](docs/heartbeat.md)
does without them.

## Documentation

- [Overview](docs/index.md)
- [Installation, in plain PHP](docs/installation.md)
- [Models and contracts](docs/models.md)
- [Tickets: the format](docs/tickets.md) and [the heartbeat](docs/heartbeat.md)
- [Seating, step by step](docs/usage-seating.md)
- [Player identity](docs/identity.md)
- [Configuration reference](docs/configuration.md)
- [Errors](docs/errors.md)
- [Testing](docs/testing.md) and [the harness](docs/harness.md)
- [Writing a provider](docs/providers.md)
- [Symfony and Laravel](docs/frameworks.md)
- [Verified, and not](docs/verified.md)

## Verified, and not

- Verified (the harness, PHP 8.4, 2026-11-08): 94 tests green on the core, omniplay/docker,
  omniplay/edgegap and omniplay/steam, PHPStan at its highest level on each, the baseline empty; every ticket vector answered
  the same by omnibase's `Base\Service\Ticket\TicketSigner` (`OmnibaseParityTest`); `BareTest`
  (nothing loads but the package and the HTTP client contracts; omnibase's `Base\` among what may
  not).
- Verified live (`docker compose run --rm -v /var/run/docker.sock:/var/run/docker.sock omniplay
  live`): a container started through Docker Desktop's socket, found running, its environment
  holding the secret, stopped and removed.
- Not verified: a game server of any engine seated end to end; a TCP or TLS engine (the requests
  are checked, not an engine); Edgegap and Steam live (no account: their requests are checked
  against answers written after their API references).

## Responsibility

`omnisense/omniplay` and its providers are independent clients of the services they reach (a
Docker Engine, a cloud's API, a platform's), affiliated with none. The MIT licence covers this code only. Whoever
runs them is responsible for the servers they start, the secrets they hand out and the players'
data they keep. The authors publish them free of charge and accept no liability for how they are
used (see [LICENSE](LICENSE)).

## Licence

MIT.
