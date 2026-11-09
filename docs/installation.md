---
title: Installation
order: 10
---

# Installation

```sh
composer require omnisense/omniplay omniplay/steam omniplay/gog     # one package per provider
```

## Requirements

- PHP 8.2 or later.
- `omnisense/omniplay` requires `symfony/http-client-contracts` (^3.0) and PHP's `mbstring`
  extension (`ext-mbstring`: the core shortens error bodies with `mb_substr()`), nothing else.
- Every provider requires `symfony/http-client` (^6.4, ^7.0 or ^8.0), a library that stands alone
  and loads no framework: its factory takes the application's client
  (`Symfony\Contracts\HttpClient\HttpClientInterface`) and, given none, makes its own
  (`HttpClient::create(['timeout' => 20])`).
- PHP's `mbstring` extension, present in most builds: Composer refuses to install on a PHP without
  it.

Omniplay runs the same in plain PHP, in a worker, in Laravel and in Symfony; there is no bundle or
Laravel provider yet ([Symfony](symfony.md), [Laravel](laravel.md)).

## Plain PHP

A whole script with no store and no network: the core's `FixedProvider` answers from records and a
library it was given, as a store would have returned them.

```php
<?php // bare.php

require __DIR__.'/vendor/autoload.php';

use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Model\Identifiers;
use Omniplay\Model\OwnedGame;
use Omniplay\Model\Platform;
use Omniplay\Model\Price;
use Omniplay\Model\Query;
use Omniplay\Model\Scheme;
use Omniplay\Registry;
use Omniplay\Testing\FixedProviderFactory;

// Records a store would have returned, and one player's library.
$games = [
    new Game('Portal 2', Identifiers::of(['steam' => 620, 'igdb' => 72]), 'fixed',
        developers: ['Valve'], platforms: Platform::fromLabels(['PC (Microsoft Windows)', 'osx', 'SteamOS']),
        price: new Price(219, 'EUR', 999, 78)),
    new Game('X-Moon', Identifiers::of(['itch' => 'leafo/x-moon']), 'fixed', price: new Price(0, 'USD')),
];
$libraries = ['76561197960287930' => [new OwnedGame(Identifiers::of(['steam' => 620]), 'Portal 2', 1260)]];

$stores = new Registry([new FixedProviderFactory($games, $libraries)], ['store' => ['factory' => 'fixed']]);

foreach ($stores->search('store')->search(new Query('portal')) as $game) {
    echo $game->title, ' ', $game->ref(Scheme::Steam), "\n";                   // "steam:620"
}

$portal = $stores->game(GameRef::steam(620));                                   // the first provider that resolves Steam numbers
printf("%s: %.2f %s (was %.2f, -%d%%), on %s\n", $portal->title, $portal->price->amount / 100, $portal->price->currency, $portal->price->initial / 100, $portal->price->discount, implode(', ', array_map(static fn ($p) => $p->value, $portal->platforms)));
var_dump($stores->game(GameRef::itch('leafo/x-moon'))?->price->isFree());

foreach ($stores->library('store')->library('76561197960287930') as $owned) {
    echo $owned->title, ': ', intdiv($owned->playtime, 60), " h played\n";
}
var_dump($stores->ownership('store')->owns('76561197960287930', GameRef::steam(620)));
```

```
$ php bare.php
Portal 2 steam:620
Portal 2: 2.19 EUR (was 9.99, -78%), on windows, mac, linux
bool(true)
Portal 2: 21 h played
bool(true)
```

(run on 2026-10-27 in the [harness](harness.md), PHP 8.4, with the harness's autoloader)

The pieces:

- a **factory** per provider package (`FixedProviderFactory`, `SteamFactory`, `GogFactory`...),
  given the HTTP client to call with;
- the **registry**, built from the factories and the providers' options, by name: `get()`, the
  typed `search()`, `catalogue()`, `library()` and `ownership()`, and `game()`, which asks each
  provider in turn;
- **references**: a `GameRef` names a game by a store and its identifier there (`GameRef::steam(620)`,
  `GameRef::itch('leafo/x-moon')`);
- the **record**: a `Game`, with what the store gives and nothing guessed - prices in minor units,
  platforms folded into families (`Platform::fromLabels()` read `osx` as `mac`, `SteamOS` as
  `linux`).

## The stores' APIs

Steam's store, GOG's catalogue and itch.io's pages need no key. This script asks each for a game's
record, with French texts and the French price where the store has them:

```php
<?php // stores.php

require __DIR__.'/vendor/autoload.php';

use Omniplay\Gog\GogFactory;
use Omniplay\Itch\ItchFactory;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Registry;
use Omniplay\Steam\SteamFactory;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create(['timeout' => 30]);
$stores = new Registry([new SteamFactory($http), new GogFactory($http), new ItchFactory($http)], [
    'steam' => ['factory' => 'steam'],                          // the store needs no key
    'gog' => ['factory' => 'gog'],
    'itch' => ['factory' => 'itch'],
]);

$show = static function (Game $g): void {
    $price = null === $g->price ? '-' : sprintf('%.2f %s%s', $g->price->amount / 100, $g->price->currency, $g->price->discount ? ' (-'.$g->price->discount.'%)' : '');
    printf("%s [%s] %s, %s, %s\n  by %s; %s\n", $g->title, json_encode($g->identifiers->toArray()), $g->released?->format('Y-m-d') ?? '-', $price,
        implode('/', array_map(static fn ($p) => $p->value, $g->platforms)), implode(', ', $g->developers) ?: '-', $g->url);
};

foreach ([['steam', GameRef::steam(620)], ['gog', GameRef::gog(1207658924)], ['itch', GameRef::itch('leafo/x-moon')]] as [$name, $ref]) {
    try {
        $show($stores->catalogue($name)->game($ref, 'fr', 'FR'));
    } catch (Omniplay\Exception\OmniplayException $e) {
        echo get_class($e), ': ', $e->getMessage(), "\n";
    }
}
```

```
$ php stores.php
Omniplay\Exception\UnreachableException: Steam could not be reached for GET /api/appdetails: TLS connect error: error:0A000126:SSL routines::unexpected eof while reading for "https://store.steampowered.com/api/appdetails?appids=620&cc=FR&l=french".
The Witcher: Enhanced Edition [{"gog":"1207658924"}] 2011-05-09, 1.19 EUR (-85%), windows/mac
  by -; https://www.gog.com/game/the_witcher
X-Moon [{"itch":"leafo\/x-moon"}] -, 0.00 USD,
  by leafo; https://leafo.itch.io/x-moon
```

(the first line as it read then: since 2026-10-29 the message ends at the client's reason,
without the URL - `... unexpected eof while reading.`; run live on 2026-10-27, 06:18 UTC, in the harness: Steam's store could not be reached from the
harness's network that morning - the TLS connection was cut, twice - which shows what a network
failure becomes; GOG answered in two calls, itch.io in one)

Steam's record, read from the answer its package's tests recorded on 2026-10-24, is in
[Store records and prices](usage-catalogue.md). The keyed providers (IGDB, RAWG, Steam's players,
itch.io's ownership) need an account: their manuals show the same calls with their options.

Building a provider calls nothing; a missing required option is reported by `create()` as an
`InvalidConfigException` naming it (`The "rawg" provider needs: api_key.`).

## In a framework

- **Symfony**: no bundle yet; the registry is a service of the application's
  ([Symfony](symfony.md)).
- **Laravel**: no provider in omnibridge/laravel yet; a service provider of the application's
  builds the registry once ([Laravel](laravel.md)).
- **Any other**: build the `Registry` once, where the framework builds its services, as the
  scripts above do.

## Online play, in plain PHP

```sh
composer require omnisense/omniplay:1.x-dev
composer require omniplay/docker:1.x-dev   # to start servers as Docker containers
composer require omniplay/edgegap:1.x-dev  # to deploy servers on Edgegap, near the players
composer require omniplay/steam:1.x-dev    # to verify players' Steam session tickets
```

PHP 8.2 or later with `json` and `mbstring`. The core requires `symfony/http-client-contracts`
alone; the Docker provider requires `symfony/http-client` (and the curl extension to reach an
engine on a unix socket).

### A first script

The harness's bare script, `docker/harness/bin/bare`, in plain PHP: a registry of the fixed
hosting provider (it starts nothing: each server is given the next port), a fleet in memory with one
server registered by hand, two players seated - the first on that server, the second on one started
for them since the first was then full - their tickets checked as a server checks them, a JSON call
answered with no network.

```php
$secrets = new FixedSecrets(['GAME_SECRET' => 'not-a-real-secret']);
$registry = new Registry([new FixedHostingFactory()], ['local' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game'], 'regions' => ['eu']]]]);
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

### With Docker

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
