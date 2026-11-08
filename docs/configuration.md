---
title: Configuration
order: 50
---

# Configuration reference

## The registry

```php
new Registry([new DockerFactory($http), new FixedHostingFactory()], [
    'docker' => ['factory' => 'docker', 'options' => [/* the provider's */]],
    'test' => ['factory' => 'fixed', 'options' => ['services' => ['game'], 'regions' => ['eu']]],
]);
```

## The core's classes

| Class | Arguments | Defaults |
|---|---|---|
| `Fleet` | store, ttl, startup, clock | 30 s, 120 s, `time()` |
| `Tickets` | secrets, clock | `time()` |
| `Seating` | fleet, tickets, hosting, secrets | no hosting: no server started; secrets required with a hosting |
| `Heartbeats` | fleet, secrets, services, clock | `time()` |
| `Service` | name, url, secret (reference), ttl, capacity, engine | null, `OMNIPLAY_TICKET_SECRET`, 60 s, 16, null |

A clock is a `\Closure(): int` giving Unix seconds.

## fixed (`Omniplay\Testing\FixedHostingFactory`)

| Option | Default | |
|---|---|---|
| `services` | `[]` (any) | the services it starts |
| `regions` | `['local']` | |
| `host`, `port` | `127.0.0.1`, 7000 | the n-th server is `host:(port + n)` |

## fixed-identity (`Omniplay\Testing\FixedIdentityFactory`)

| Option | Default | |
|---|---|---|
| `players` | `[]` | the player's id by proof |
| `platform` | `test` | |

## docker (`omniplay/docker`)

See its README: `services` (required; by service name: `image`, `port`, `env`, `command`,
`address`, `network`, `labels`, `host_port`, `memory`, `cpus`), `host`, `api_version`,
`public_host`, `region`, `secret_env`, `name_prefix`, `auto_remove`, `stop_timeout`, `tls`,
`heartbeat_url`, `user_agent`.

## edgegap (`omniplay/edgegap`)

See its README: `services` (required; by service name: `application`, `version`, `port`,
`address`, `env`, `internal_port`, `cpu_units`, `memory_mib`, `require_cached_locations`, `tags`),
`token` (the API token's reference), `regions`, `api`, `secret_env`, `name_prefix`,
`heartbeat_url`, `wait`, `poll_interval`, `user_agent`.

## steam (`omniplay/steam`)

See its README: `appid` (required), `key` (the publisher Web API key's reference), `identity`,
`refuse_banned`, `api`, `user_agent`.

## What a server is handed

A server started by a provider finds in its environment, whatever its engine:

| Variable | |
|---|---|
| `OMNIPLAY_TICKET_SECRET` (or the provider's `secret_env`) | the secret its tickets are checked with |
| `OMNIPLAY_SERVER_ID`, `OMNIPLAY_ROOM` | its id, the room its tickets name |
| `OMNIPLAY_SERVICE`, `OMNIPLAY_CAPACITY`, `OMNIPLAY_PORT` | its service, seats, the port it listens on |
| `OMNIPLAY_ENGINE` | the service's declared engine, when set |
| `OMNIPLAY_HEARTBEAT_URL` | where to send its heartbeats, when configured |

The providers that run in the game server read these by default.
