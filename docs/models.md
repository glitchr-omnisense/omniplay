---
title: Models and contracts
order: 20
---

# Models and contracts

## Contracts

| Contract | Implemented by | What it answers |
|---|---|---|
| `FleetInterface` | `Fleet` (over a `StoreInterface`) | `register()`, `heartbeat()` (null when unknown), `get()`, `servers()` of a service, `healthy()` ones, `available()` ones (a seat left; healthy within the ttl or starting within the startup's wait), `occupy()` (one more player, null when full, draining or down), `retireStale()` (the removed servers), `remove()` |
| `SeatingInterface` | `Seating` | `seat(Service, SeatRequest): ?Seat` |
| `TicketsInterface` | `Tickets` (over a `SecretsInterface`) | `mint(Service, room, claims): Ticket`, `verify(Service, token, room): ?Ticket` |
| `HostingInterface` | providers (`omniplay/docker`, `Testing\FixedHosting`) | `start(Service, secret, region, tags): Server` (Starting, not registered), `stop(Server)`, `running(service)` |
| `ProviderInterface` | every provider | `getName()`, `getTitle()`, `capabilities()` |
| `Storage\StoreInterface` | `InMemoryStore`, the application's own | `save()`, `find()`, `all()`, `delete()` |
| `Secret\SecretsInterface` | `EnvironmentSecrets`, `FixedSecrets`, the application's own (a vault) | `secret(reference): string` |

`Heartbeats` is no contract: it is the application's end of a server's heartbeat
([the heartbeat](heartbeat.md)).

## Capabilities

`Capabilities(hosting, services, regions, where)`: whether the provider starts servers, of which
services (any when empty), in which regions (any when empty), and where it runs them for a person
to read. `starts($service, $region)` is what `Seating` and `Registry::hostingFor()` ask.

## The registry

`new Registry($factories, $config)`: `$config` maps a name to `['factory' => ..., 'options' =>
[...]]`. `get()`, `has()`, `hosting($name)` (a `NotSupportedException` when the provider starts
nothing), `hostingFor($service, $region, ...$names)` (the first that starts it there, or null),
`options()`, `names()`, `factories()`.

## Server

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

## Service

`new Service($name, $url = null, $secret = 'OMNIPLAY_TICKET_SECRET', $ttl = 60, $capacity = 16,
$engine = null)`; `fromArray($name, $values)` reads a configuration's entry. `$secret` is a
reference, read through a `SecretsInterface`; `$url` is the public address where tickets are
presented when the service has one of its own (a relay).

## Seat, Ticket, SeatRequest, HeartbeatResult

- `SeatRequest($player, $region = null, $tags = [], $claims = [])`.
- `Seat($player, Server, Ticket, $started)`: `toArray()` is what the client is handed (address,
  ticket, expiry in Unix seconds, server, service, region, engine, started).
- `Ticket($token, $room, $expires, $claims)`: the claims without `room` and `exp`.
- `HeartbeatResult($status, $reason, ?Server)`: `accepted()`, `toArray()` the response's body.

## The engine

`Service::$engine` and `Server::$engine` are free strings the application declares:
`unity-mirror`, `unity-netcode`, `godot`, `node`, `custom`, or anything else. Omniplay stores them,
returns them (in `Seat::toArray()`, a Docker server's `OMNIPLAY_ENGINE`), and never branches on
them: it does not detect what a server runs.
