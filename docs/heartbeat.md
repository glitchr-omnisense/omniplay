---
title: The heartbeat
order: 35
---

# The heartbeat

A server tells the application it is alive, how many players it holds and whether it takes more,
every few seconds (10 by the providers' default, under the fleet's ttl of 30). The application's
route hands the request to `Omniplay\Heartbeats::receive()` and answers with what it returns.

## The request

```
POST <the application's heartbeat URL>          ; OMNIPLAY_HEARTBEAT_URL in a Docker server's environment
Authorization: Bearer <token>
Content-Type: application/json

{"server": "omniplay-game-3fa9c2d1", "service": "game", "players": 5, "health": "healthy"}
```

- **The token** is a ticket ([format](tickets.md)) signed with the service's secret, its payload
  `{"room": "heartbeat:<server id>", "exp": <now + 60>}`. `Heartbeats::room()` and `::token()`
  make them in PHP; the providers that run in the game server make the same bytes (the vector "a server's heartbeat token").
  A player's ticket names the server's id as its room, never `heartbeat:...`: it cannot pass for
  a heartbeat.
- **The body**: `server` and `service` (required), `players` (0 when absent), `health`
  (`starting`, `healthy`, `draining`, `down`; `healthy` when absent or unknown). A server the fleet
  does not know adds `address` (required then), and may add `capacity` (the service's otherwise),
  `region`, `tags` (a list) and `engine` (the service's otherwise): it is registered.

## The answers

| Status | When | Body's reason |
|---|---|---|
| 200 | heard: the fleet holds the players and health sent | `Heard.` |
| 201 | registered: unknown, with an address | `Registered.` |
| 400 | no `server` or `service`; a value no server can hold (capacity 0) | the reason |
| 401 | the token does not hold for `heartbeat:<server>` with the service's secret (another secret, expired, another server's, a player's ticket) | `The heartbeat's token does not hold for this server.` |
| 404 | the service is not one the application gave `Heartbeats`; or the server is unknown and gave no address | the reason |
| 409 | the server is known under another service | the reason |

`HeartbeatResult::toArray()` is the body: `{"status": 200, "reason": "Heard.", "server": {"id":
"...", "players": 5, "health": "healthy"}}`. Nothing secret.

## The route, in plain PHP

```php
$heartbeats = new Heartbeats($fleet, new EnvironmentSecrets(), [$game]);
$result = $heartbeats->receive($_SERVER['HTTP_AUTHORIZATION'] ?? '', json_decode(file_get_contents('php://input'), true) ?: []);
http_response_code($result->status);
header('Content-Type: application/json');
echo json_encode($result->toArray());
```

A server that says `down` is retired at the next `Fleet::retireStale()`; one that says `draining`
keeps its players and takes no new one.
