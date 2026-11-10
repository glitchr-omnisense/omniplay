# Usage policies

Every omniplay provider carries a usage policy: omnisense/contracts' `UsagePolicy`
([its schema and rules](https://github.com/glitchr-omnisense/contracts/blob/1.x/docs/policies.md)).

| Provider | Declared (`policy.json`) | Rate limits | Rights |
|---|---|---|---|
| omniplay/steam | the Web API terms could not be read (2026-11-10) | unknown (100,000 calls a day per key is quoted elsewhere, not declared) | unknown; Valve's trademark line credited |
| omniplay/gog | the User Agreement, read on 2026-11-10 (no API terms) | unknown | unknown; 11.1(e) forbids bots and extraction tools: ask GOG first |
| omniplay/itch | the terms and the server-side API page, read on 2026-11-10 | not stated | not stated |
| omniplay/igdb | api-docs.igdb.com and Twitch's Developer Agreement, read on 2026-11-10 | 4 requests a second (and 8 open at most) | commercial use under contract, storage allowed, redistribution forbidden, IGDB.com credited |
| omniplay/rawg | rawg.io could not be reached (2026-11-10) | unknown (20,000 a month is quoted elsewhere, not declared) | a key, RAWG credited with a link on every page, no cloning |
| omniplay/edgegap | its API docs and terms, read on 2026-11-10 | 40 deployments a second (start); status reads 20 a second, inside start | a paid service: under contract |
| omniplay/docker | the site's own engine | none | allowed (Docker Engine under Apache-2.0; Docker Desktop's subscription terms noted) |

## Where calls are checked

`Registry::search()`, `catalogue()`, `library()` and `ownership()` give the provider behind its
guard (`Omniplay\Policy\GuardedSearch`...). Each call is one operation, named after its method:
`search`, `game`, `library`, `owns`. `Registry::game()` asks through them. A limit with
`operations` counts only those; one without counts every call.

`hosting()`, `hostingFor()`, `identity()` and `identityFor()` give the provider behind its guard
(`GuardedHosting`: `start`, `stop`, `running`; `GuardedIdentity`: `verify`, its `platform()`
passed through) when the policy, as the site runs it, limits one of those operations, and the
provider itself otherwise: a Docker Engine of one's own counts nothing. Either way its HTTP client
honours a 429's wait. `Registry::get()` gives the provider itself, unguarded.

A call over a limit is never made: `UsageLimitReachedException` (an `OmniplayException`), with
`retryAfter` in seconds and the `limit` reached. A provider's own refusal - a 429, a 503 with
`Retry-After`, a `RateLimit: "...";r=0;t=30` field - holds every following call of that provider
until the wait is over.

## A site's configuration

```php
$games = new Registry($factories, [
    'igdb' => [
        'factory' => 'igdb',
        'options' => ['client_id' => '...', 'client_secret' => '...'],
        'policy' => ['rateLimits' => [['max' => 2, 'window' => 'second', 'scope' => 'key']]],
        'usage' => ['commercial' => false],
    ],
    'cloud' => [
        'factory' => 'edgegap',
        'options' => ['services' => [...], 'regions' => ['eu' => '198.51.100.20']],
        'policy' => ['rateLimits' => [['max' => 100, 'window' => 'hour', 'operations' => ['start']]]],
    ],
], $limiter);
```

- `policy` tightens the provider's: lower limits are added, stricter rights replace the
  declared ones. A looser value (10 a second where IGDB says 4) is refused with
  `InvalidPolicyException` when the provider is first used.
- `usage` says what the site is: a commercial site is refused at once where the terms forbid it
  (`UsageNotPermitted`).
- Both may sit in `options` too.
- `$limiter` (`RateLimiterInterface`): an in-memory one by default, which counts within one PHP
  process. A web application gives a shared one, or the counts start over with every request.

`$games->policy('igdb')` and `$games->policies()` give the policies as the site runs them, for a
page listing the data's terms. `$games->guard('igdb')->remaining('game')` gives the calls left.

## Older Capabilities

`Capabilities` keeps `$attribution`, `$terms` and `$keyless` (deprecated), as given. A provider that
gives them alone gets a policy made of them, its limits unknown (`UsagePolicy::described()`).
`toArray()` keeps its shape; the policy is read with `policy()->toArray()`.
