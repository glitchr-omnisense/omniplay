# omnisense/omniplay

Omniplay is one PHP contract for online play on the platform side: which game servers are up, which
one a player goes on, and the ticket that lets them in. It knows no engine and no netcode: a server
is an address, a capacity, a count of players and a health; what it runs is a string the
application declares (`engine`), stored and returned as it is.

```php
$seat = $seating->seat($game, new SeatRequest('player-42', 'eu', ['ranked']));   // ?Seat
$seat->toArray();            // address, ticket, expiry: what the client is handed
$fleet->heartbeat('eu-1', 5);                                                      // a server says it holds 5 players
$fleet->retireStale();       // the servers fallen silent, removed: stop those a provider started
$heartbeats->receive($request->headers->get('Authorization', ''), $request->toArray());   // a server's own heartbeat
```

The design is the one of the other Omnisense families: a provider's **factory** fills a
configuration with its name, its required options and the defaults of the others, then builds the
**provider**; a **registry** holds the application's providers by name; a provider says what it can
be asked in its **capabilities** (here: whether it starts servers, of which services, in which
regions). The core needs no framework: it requires `symfony/http-client-contracts` and nothing
else.

## The flow

1. A server starts - by hand, or through a hosting provider (`HostingInterface::start()`), which
   hands it its ticket secret in its environment - and is registered in the fleet.
2. It sends heartbeats (players, health) to the application's route, which hands them to
   `Heartbeats::receive()`; a server the fleet does not know registers itself with its address.
3. A player asks the application to play. `Seating::seat()` finds the fullest server of the
   service that still has a seat (a ready one first, then one starting), or starts one, counts the
   player on it, and mints a ticket whose room is the server's id.
4. The client connects to the address and presents the ticket; the server verifies it with the
   secret (signature, room, expiry) and lets the player in or not. It never calls the application.
5. `Fleet::retireStale()` removes the servers fallen silent; the application stops those a
   provider started.

## When to use it

- A game whose dedicated servers run as containers on one machine or a few, started as players
  come.
- A relay or a lobby service (a WebSocket server) that must let in only the players the site
  signed in: one `Service` with its public URL, the tickets alone.
- A server run by hand at home or on a rented machine, registering itself by its heartbeats.

It does no netcode, no matchmaking by skill, no billing. It stores nothing itself: the fleet's
store is the application's.

## Providers

On the platform, omniplay/docker starts and stops servers as containers, omniplay/edgegap as
Edgegap deployments near the players; omniplay/steam verifies a player's Steam session ticket
([Player identity](identity.md), optional). Inside the game server -
not PHP - omniplay/dotnet (any .NET, Unity included), omniplay/mirror and omniplay/netcode (Unity's
two netcodes, over omniplay/dotnet), omniplay/godot (GDScript) and omniplay/node verify the tickets
and send the heartbeats. They call omniplay; omniplay never calls them, and each is checked against
`Tests/vectors.json`. See [Tickets](tickets.md) and [the heartbeat](heartbeat.md) for a server
written in anything else.

## How this manual is organised

- [Installation](installation.md): requirements, a first script in plain PHP.
- [Models and contracts](models.md).
- [Tickets](tickets.md): the format, field by field, with its vectors.
- [The heartbeat](heartbeat.md): the protocol between a server and the application.
- [Seating](usage-seating.md): how a server is chosen, started, and a ticket minted.
- [Player identity](identity.md): a player verified by a platform, seated under that id.
- [Configuration](configuration.md): the registry's, the core classes' and the fixed provider's.
- [Errors](errors.md).
- [Testing](testing.md) and [the harness](harness.md).
- [Writing a provider](providers.md).
- [Symfony and Laravel](frameworks.md).
- [Verified, and not](verified.md).
