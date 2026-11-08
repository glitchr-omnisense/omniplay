---
title: Seating
order: 40
---

# Seating, step by step

```php
$seating = new Seating($fleet, new Tickets($secrets), $hosting, $secrets);
$seat = $seating->seat($game, new SeatRequest('player-42', region: 'eu', tags: ['ranked'], claims: ['name' => 'Ada']));
```

1. **The candidates**: `FleetInterface::available($service, $region)` - the servers of the service
   in the region asked (any, when null), with a seat left, healthy and heard from within the
   fleet's ttl (30 s), or starting and registered within its startup wait (120 s) - whose tags hold
   every tag asked.
2. **The order**: ready servers before starting ones, then the fullest first, then by id. Matches
   fill before new servers are used, and a server started for the previous player is used by the
   next one rather than another started.
3. **The seat**: `occupy()` counts one more player on the first candidate that still has room
   (the next heartbeat gives the true count). None has room: when a hosting is given and its
   capabilities say it starts the service in that region, a server is started, the service's
   secret handed to it, registered and occupied (`Seat::$started`, the client retries its
   address until the ticket expires). Otherwise the answer is null.
4. **The ticket**: `Tickets::mint()`, room the server's id, claims `sub` (the player, which a
   claim of that name does not replace) then the request's claims, `exp` now plus the service's
   ttl.

`$seat->toArray()` is what the client is handed. The server checks the ticket with
`OMNIPLAY_ROOM` (its id) as the room.

## Retiring

```php
foreach ($fleet->retireStale() as $server) {
    if (null !== $server->provider && $hosts->has($server->provider)) {
        $hosts->hosting($server->provider)->stop($server);
    }
}
```

Run it every half minute (a scheduler, a cron). A server is stale after 30 s without a heartbeat
(120 s while starting) or when it said `down`. `Server::$provider` is the provider's own name
(`docker`): configure it under the same name in the registry, or keep a map of your own.

## After a restart

An `InMemoryStore` is gone with its process; the application's own store survives it. A provider's
`running()` lists what it runs, to register again:

```php
foreach ($hosts->hosting('docker')->running('game') as $server) {
    $fleet->get($server->id) ?? $fleet->register($server);
}
```
