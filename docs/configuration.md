---
title: Configuration reference
order: 40
---

# Configuration reference

## The registry's configuration

`new Registry($factories, $config)` takes the providers by name, each with its factory and options:

```php
[
    '<provider name>' => [
        'factory' => '<factory name>',     // required: a factory's getName()
        'options' => [/* ... */],          // optional, default []: handed to the factory's create()
    ],
]
```

The order matters to `Registry::game()` called without names. Options are checked when the provider
is first asked for. Every factory takes an optional HTTP client in its constructor and makes its own
(`HttpClient::create(['timeout' => 20])`) when given none. An empty string is the same as an option
not given.

## omniplay/steam

Factory `Omniplay\Steam\SteamFactory`, name `steam`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `key` | string | `null` | no | a Steam Web API key: only for players' libraries and ownership; sent in the query string (`key=`) |
| `language` | string | `english` | no | the store's language: Steam's name (`french`) or an ISO code (`fr`, `pt-BR`, `zh-TW`: 17 mapped) |
| `country` | string | `US` | no | the prices' country (`cc`) |
| `user_agent` | string | `omniplay/1.0 (+https://github.com/glitchr-omnisense/omniplay)` | no | sent with every request |
| `store` | string | `https://store.steampowered.com` | no | the storefront |
| `api` | string | `https://api.steampowered.com` | no | the Web API |
| `appid` | int | `null` | for identity | the game's Steam app id (an unsigned 32-bit number); without it, the provider verifies no player |
| `publisher_key` | string | `STEAM_PUBLISHER_KEY` | for identity | the publisher Web API key's reference, read through the factory's `SecretsInterface` (the environment by default) at each call |
| `identity` | string | `null` | no | the string the game passes to `GetAuthTicketForWebApi`; Steam then authenticates only the tickets made with it |
| `refuse_banned` | bool | `false` | no | a player banned by VAC or by the publisher is refused rather than answered with the ban in the claims |
| `partner_api` | string | `https://partner.steam-api.com` | no | the host `AuthenticateUserTicket` is asked of (`https://api.steampowered.com` accepted) |

## omniplay/gog

Factory `Omniplay\Gog\GogFactory`, name `gog`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `locale` | string | `en-US` | no | the texts' locale; a two-letter code is widened (`en` -> `en-US`, `fr` -> `fr-FR`) |
| `country` | string | `US` | no | the prices' country |
| `currency` | string | `USD` | no | the search's prices; a record's price is in the country's own currency |
| `user_agent` | string | `omniplay/1.0 (+https://github.com/glitchr-omnisense/omniplay)` | no | |
| `api` | string | `https://api.gog.com` | no | products and prices |
| `catalog` | string | `https://catalog.gog.com` | no | the search |

## omniplay/itch

Factory `Omniplay\Itch\ItchFactory`, name `itch`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `key` | string | `null` | no | a server-side API key (account settings, or OAuth): only for ownership and library; sent as `Authorization: Bearer` |
| `user_agent` | string | `omniplay/1.0 (+https://github.com/glitchr-omnisense/omniplay)` | no | |
| `api` | string | `https://api.itch.io` | no | the server-side API (pages are read from `https://{creator}.itch.io`) |

## omniplay/igdb

Factory `Omniplay\Igdb\IgdbFactory`, name `igdb`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `client_id` | string | - | yes | a Twitch application's client id, sent as `Client-ID` |
| `client_secret` | string | - | yes | its secret, for the client-credentials token |
| `api` | string | `https://api.igdb.com/v4` | no | |
| `token_url` | string | `https://id.twitch.tv/oauth2/token` | no | |
| `images` | string | `https://images.igdb.com/igdb/image/upload` | no | covers and screenshots |

## omniplay/rawg

Factory `Omniplay\Rawg\RawgFactory`, name `rawg`.

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `api_key` | string | - | yes | sent in the query string (`key=`) |
| `user_agent` | string | `omniplay/1.0 (+https://github.com/glitchr-omnisense/omniplay)` | no | |
| `api` | string | `https://api.rawg.io/api` | no | |

## Per call

A call's `language` and `country` replace the provider's options for that call; the `Query` carries
them for a search ([Models](models.md#query)).

## The fixed provider (testing)

`Omniplay\Testing\FixedProviderFactory`, name `fixed`, takes its games and libraries in its
constructor (`new FixedProviderFactory($games, $libraries)`) and no option.

## Online play: the core classes, hosting and identity

### The registry

```php
new Registry([new DockerFactory($http), new FixedHostingFactory()], [
    'docker' => ['factory' => 'docker', 'options' => [/* the provider's */]],
    'test' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game'], 'regions' => ['eu']]],
]);
```

### The core's classes

| Class | Arguments | Defaults |
|---|---|---|
| `Fleet` | store, ttl, startup, clock | 30 s, 120 s, `time()` |
| `Tickets` | secrets, clock | `time()` |
| `Seating` | fleet, tickets, hosting, secrets | no hosting: no server started; secrets required with a hosting |
| `Heartbeats` | fleet, secrets, services, clock | `time()` |
| `Service` | name, url, secret (reference), ttl, capacity, engine | null, `OMNIPLAY_TICKET_SECRET`, 60 s, 16, null |

A clock is a `\Closure(): int` giving Unix seconds.

### fixed-hosting (`Omniplay\Testing\FixedHostingFactory`)

| Option | Default | |
|---|---|---|
| `services` | `[]` (any) | the services it starts |
| `regions` | `['local']` | |
| `host`, `port` | `127.0.0.1`, 7000 | the n-th server is `host:(port + n)` |

### fixed-identity (`Omniplay\Testing\FixedIdentityFactory`)

| Option | Default | |
|---|---|---|
| `players` | `[]` | the player's id by proof |
| `platform` | `test` | |

### docker (`omniplay/docker`)

See its README: `services` (required; by service name: `image`, `port`, `env`, `command`,
`address`, `network`, `labels`, `host_port`, `memory`, `cpus`), `host`, `api_version`,
`public_host`, `region`, `secret_env`, `name_prefix`, `auto_remove`, `stop_timeout`, `tls`,
`heartbeat_url`, `user_agent`.

### edgegap (`omniplay/edgegap`)

See its README: `services` (required; by service name: `application`, `version`, `port`,
`address`, `env`, `internal_port`, `cpu_units`, `memory_mib`, `require_cached_locations`, `tags`),
`token` (the API token's reference), `regions`, `api`, `secret_env`, `name_prefix`,
`heartbeat_url`, `wait`, `poll_interval`, `user_agent`.

### steam (`omniplay/steam`)

Its identity options - `appid`, `publisher_key`, `identity`, `refuse_banned`, `partner_api` - are in
[omniplay/steam](#omniplaysteam) above, beside the store's.

### What a server is handed

A server started by a provider finds in its environment, whatever its engine:

| Variable | |
|---|---|
| `OMNIPLAY_TICKET_SECRET` (or the provider's `secret_env`) | the secret its tickets are checked with |
| `OMNIPLAY_SERVER_ID`, `OMNIPLAY_ROOM` | its id, the room its tickets name |
| `OMNIPLAY_SERVICE`, `OMNIPLAY_CAPACITY`, `OMNIPLAY_PORT` | its service, seats, the port it listens on |
| `OMNIPLAY_ENGINE` | the service's declared engine, when set |
| `OMNIPLAY_HEARTBEAT_URL` | where to send its heartbeats, when configured |

The providers that run in the game server read these by default.
