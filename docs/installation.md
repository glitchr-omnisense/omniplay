---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omniplay:1.x-dev
composer require omniplay/docker:1.x-dev   # to start servers as Docker containers
composer require omniplay/edgegap:1.x-dev  # to deploy servers on Edgegap, near the players
composer require omniplay/steam:1.x-dev    # to verify players' Steam session tickets
```

PHP 8.2 or later with `json` and `mbstring`. The core requires `symfony/http-client-contracts`
alone; the Docker provider requires `symfony/http-client` (and the curl extension to reach an
engine on a unix socket).

## A first script

The harness's bare script, `docker/harness/bin/bare`, in plain PHP: a registry of the fixed
hosting provider (it starts nothing: each server is given the next port), a fleet in memory with one
server registered by hand, two players seated - the first on that server, the second on one started
for them since the first was then full - their tickets checked as a server checks them, a JSON call
answered with no network.

```php
$secrets = new FixedSecrets(['GAME_SECRET' => 'not-a-real-secret']);
$registry = new Registry([new FixedHostingFactory()], ['local' => ['factory' => 'fixed', 'options' => ['services' => ['game'], 'regions' => ['eu']]]]);
$service = new Service('game', secret: 'GAME_SECRET', ttl: 60, capacity: 2, engine: 'custom');
$fleet = new Fleet(new InMemoryStore());
$fleet->register(new Server('eu-1', 'game', '203.0.113.7:7777', 2, region: 'eu', engine: 'custom'));
$fleet->heartbeat('eu-1', 1);
$seating = new Seating($fleet, new Tickets($secrets), $registry->hosting('local'), $secrets);

$first = $seating->seat($service, new SeatRequest('player-1', 'eu'));
$second = $seating->seat($service, new SeatRequest('player-2', 'eu'));
TicketSigner::verify($second->ticket->token, 'not-a-real-secret', $second->server->id)['sub'];   // player-2
TicketSigner::verify($second->ticket->token, 'not-a-real-secret', 'eu-1');                       // null: another room
```

`docker compose run --rm omniplay bare`, on 2026-11-08 (the report, without the classes and files
loaded):

```json
{
  "capabilities": {"contracts": ["hosting"], "services": ["game"], "regions": ["eu"], "where": "nowhere: 127.0.0.1, in memory"},
  "seats": [["eu-1", "203.0.113.7:7777", false], ["game-1", "127.0.0.1:7001", true]],
  "checked": "player-2",
  "refused": null,
  "json": ["eu-1", 3, "omniplay-bare/1.0"],
  "json-404": null,
  "json-error": "Example answered 500 for GET /servers/3. Internal error"
}
```

## With Docker

```php
$hosts = new Registry([new DockerFactory()], ['local' => ['factory' => 'docker', 'options' => [
    'host' => 'unix:///var/run/docker.sock',
    'public_host' => 'play.example.org',
    'heartbeat_url' => 'https://www.example.org/omniplay/heartbeat',
    'services' => ['game' => ['image' => 'example/game-server:1.4', 'port' => '7777/udp', 'env' => ['MAP' => 'harbour']]],
]]]);
```

The image must be on the engine already (`docker pull`); see omniplay/docker's README for every
option.
