---
title: Testing
order: 60
---

# Testing

Testing an application that reads stores with Omniplay. Writing a provider and its tests:
[Writing a provider](providers.md).

## The fixed provider as a fake

`Omniplay\Testing\FixedProvider` answers all four contracts from games and libraries given in
advance, with no network; `FixedProviderFactory($games, $libraries)` registers it under the name
`fixed`. What it does:

- **capabilities**: every contract, every scheme, prices, `player: 'any name'`, keyless;
- **search**: the games whose title, lower-cased, contains every word of the query, cut to the
  limit (language and country ignored);
- **game()**: the first game whose identifier in the ref's scheme is the ref's; else `null`;
- **library()**: the list given under that player's name; else `[]`;
- **owns()**: whether that library holds a game with the ref's identifier.

## A provider on recorded answers

`Omniplay\Testing\RecordedHttpClient` (the same as Omnifilm's) answers a provider's calls with no
network: `RecordedHttpClient::routes([regex => [status, body]])` matched against `"METHOD url"`
with the query, a body `"@/path/file.json"` read from that file, 404 for anything else; or `new
RecordedHttpClient(fn ($method, $url, $options) => [status, body])`. `$requests` keeps every
request, `header($name, $request = -1)` a header as sent. It does not stream - no Omniplay
provider needs it to.

```php
use Omniplay\Model\GameRef;
use Omniplay\Model\Identifiers;
use Omniplay\Model\OwnedGame;
use Omniplay\Registry;
use Omniplay\Steam\SteamFactory;
use Omniplay\Testing\FixedProviderFactory;
use Omniplay\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

final class AccessTest extends TestCase
{
    public function testAPlayerWhoOwnsTheGameGetsIn(): void
    {
        $stores = new Registry([new FixedProviderFactory(libraries: [
            '76561197960287930' => [new OwnedGame(Identifiers::of(['steam' => 620]), 'Portal 2')],
        ])], ['steam' => ['factory' => 'fixed']]);

        self::assertTrue($stores->ownership('steam')->owns('76561197960287930', GameRef::steam(620)));
        self::assertFalse($stores->ownership('steam')->owns('76561197960287931', GameRef::steam(620)));
    }

    public function testWhatSteamIsAsked(): void
    {
        $http = RecordedHttpClient::routes([
            '~/IPlayerService/GetOwnedGames/v1/\?key=test-key&steamid=76561197960287930~' => [200, '{"response":{"games":[{"appid":620,"name":"Portal 2","playtime_forever":90}]}}'],
        ]);
        $steam = (new SteamFactory($http))->create(['key' => 'test-key']);

        self::assertSame(90, $steam->library('76561197960287930')[0]->playtime);
        self::assertSame('omniplay/1.0 (+https://github.com/glitchr-omnisense/omniplay)', $http->header('User-Agent'));
    }
}
```

```
$ vendor/bin/phpunit AccessTest.php
..                                                                  2 / 2 (100%)

OK (2 tests, 4 assertions)
```

(run on 2026-10-27 in the [harness](harness.md), PHPUnit 11.5, PHP 8.4)

A network error is tested with Symfony's `MockHttpClient` and a `MockResponse` whose `error` option
is set: it becomes an `UnreachableException`.

## The bare test case

`Omniplay\Testing\BareTestCase` is the check every package of the family runs in its `BareTest`:
each of its classes loaded in a PHP process of its own, with nothing from outside but
`omnisense/omniplay` and what the package requires. A package names its `entryClass()` and its
`requiredPackages()` (`symfony/http-client-contracts` for every provider); `allowedNamespaces()`
lists namespaces `FOREIGN` matches that its requirements may load (none is declared). `FOREIGN`:
Symfony's classes but its contracts and polyfills, Illuminate, Laravel, Doctrine, Twig, League,
Guzzle, Nyholm, Http, Omnibridge, Base, App. A second test checks that the report is not blind (a
`MockHttpClient` loaded on purpose is seen). Installed in an application, the check is skipped.

## In a framework's functional test

The registry is a service of the application's: in Symfony, define it with `FixedProviderFactory`
in `config/services_test.yaml`, or replace `http_client` with a `MockHttpClient` in the test
environment; in Laravel, bind another `Registry` singleton in the test's `setUp()`.

## Online play

- `Omniplay\Testing\FixedHosting` (factory `fixed-hosting`): a hosting provider that starts nothing. The
  n-th server is `<service>-n` at `host:(port + n)`, Starting; `$started`, `$stopped` and
  `$secrets` (the secret each was handed) are public for a test to read.
- `Omniplay\Testing\FixedIdentity` (factory `fixed-identity`, options `players`, the player's id by
  proof, and `platform`): a platform that vouches for the players it was given and refuses any
  other proof; `$asked` keeps every proof.
- `Storage\InMemoryStore` and `Secret\FixedSecrets`.
- A clock: `Fleet`, `Tickets` and `Heartbeats` take a `\Closure(): int`.
- `Testing\RecordedHttpClient`: an HTTP client answering by route, keeping every request
  (`$requests`, `header()`); a provider's calls checked with no network.
- `Testing\BareTestCase`: each class of a package loaded in a PHP process of its own with nothing
  but omniplay and what the package requires (no framework, no omnibase).
- `Tests/vectors.json`: the tickets every verifier is checked against ([Tickets](tickets.md)).

```php
$now = 1800000000;
$fleet = new Fleet(new InMemoryStore(), 30, 120, function () use (&$now): int { return $now; });
$fleet->register(new Server('a', 'game', '127.0.0.1:7777', 8));
$fleet->heartbeat('a', 3);
$now += 31;
$fleet->healthy('game');   // []
```
