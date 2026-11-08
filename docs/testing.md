---
title: Testing
order: 70
---

# Testing

- `Omniplay\Testing\FixedHosting` (factory `fixed`): a hosting provider that starts nothing. The
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
