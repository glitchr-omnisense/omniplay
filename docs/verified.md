---
title: Verified, and not
order: 90
---

# Verified, and not

## After the merge (2026-11-09)

The omnigame family merged into omniplay. In the [harness](harness.md), every PHP package from its
checkout, PHP 8.4.27, PHPUnit 11.5: 145 tests, 760 assertions, green (the `live` group left out). By
package, live tests included: the core 75 (its 62 before and omnigame core's 16, the two bare tests
now one), omniplay/steam 18 (the identity's 8 and the store's 10, one `BareTest` for both),
omniplay/docker 11, omniplay/edgegap 13, omniplay/itch 14, omniplay/gog 7, omniplay/igdb 6,
omniplay/rawg 6. PHPStan at its highest level, the baseline empty, on the core, steam, docker,
edgegap, gog and rawg; igdb and itch keep one error each in their tests, there before the merge.
`OmnibaseParityTest` ran against omnibase's `TicketSigner`, all 27 vectors the same. Inside the game
server, unchanged by the merge: omniplay/node 35 tests green (Node 26.12), omniplay/dotnet 36 (.NET
SDK 10.0.403); their vectors and godot's identical to `Tests/vectors.json` (cmp); omniplay/mirror
and omniplay/netcode compiled against stubs on 2026-11-08, not again; omniplay/godot not run (no
Godot binary).

## The stores, as verified before the merge

Run on 2026-10-27 in the [harness](harness.md), every package from its checkout, PHP 8.4,
PHPUnit 11.5: 44 tests, 245 assertions, green; PHPStan at its highest level on the six packages, no
error.

- The core: identifiers, references, the query's limits, a game's refs, pictures and platforms,
  `Platform::fromLabels()`, capabilities and `resolves()`, the registry (providers built once, a
  capability lacking, a missing option, `game()` asking the first provider that resolves), `Data`
  and `JsonClient`; a `BareTest` loading nothing but Omniplay and the HTTP client contracts.
- Each provider: its calls and its reading of the answers, unknown as `null`, a refusal as an
  exception, and a `BareTest`.

| Provider | Answers its tests use |
|---|---|
| steam | the store's answers recorded on 2026-10-24 (Portal 2 in French, an unknown app, a search); the library on an answer shaped after the Web API's documentation |
| gog | recorded on 2026-10-24 (The Witcher in French with its French price, an unknown product, a search) |
| itch | X-Moon's page recorded on 2026-10-24; ownership and library shaped after the server-side API's documentation |
| igdb | shaped after IGDB's documentation, written by hand |
| rawg | shaped after RAWG's documentation, written by hand |

There is no conformance suite shared by the providers.

## What was run live

- `docker compose run --rm omniplay live`, 2026-10-24 (README): Steam's store (Portal 2's record
  and price, a search), GOG's catalogue (a product, its price, a search), an itch.io page.
- For this manual, 2026-10-27, 06:17-06:24 UTC: GOG's product and price (The Witcher in France, two
  calls, twice) and itch.io's page (X-Moon, one call) answered; Steam's store could not be reached from
  the harness's network (the TLS connection cut, twice): its record in this manual is the recorded
  one.

## What is not verified

- Steam's library and ownership, itch.io's ownership and library, IGDB and RAWG: no key was used.
- IGDB's move of external games from `category` to `external_game_source`: both are read, the lookup
  asks the latter - not seen live.
- How itch.io answers "no download key found" (its status): read as "not owned" whatever the status.
- The [Symfony](symfony.md) and [Laravel](laravel.md) pages were written from the classes'
  constructors, not run.

## Known defects

None left: those the manuals found were fixed on 2026-10-29.

## Responsibility

`omnisense/omniplay` and its `omniplay/*` providers are independent, unofficial clients: none is
affiliated with, endorsed by or sponsored by the stores and services it reaches. Each keeps its own
terms (accounts and keys, rate limits, commercial use, trademarks, players' data), named in the
README and the manual of each provider. The MIT licence covers this code only and grants no right
to any service or to its data; whoever runs these packages complies with those terms.

## Online play, as verified before the merge (2026-11-08)

Before the merge, online play alone:

- **The harness** (PHP 8.4.27): 94 tests green - the core 62, omniplay/docker 10,
  omniplay/edgegap 12, omniplay/steam 10 - and PHPStan at its highest level on each with an empty
  baseline. Among them: every vector of `Tests/vectors.json`
  (5 signed, 22 verified) signed and verified by `TicketSigner`, and answered the same by
  omnibase's `Base\Service\Ticket\TicketSigner` (`OmnibaseParityTest`, omnibase mounted read-only);
  `BareTest` (nothing loaded but omniplay and the HTTP client contracts), in each of the four
  packages.
- **The identity capability** (`IdentityTest`): a seat under a verified `Identity`, its ticket's
  `sub` the platform's id; the registry's `identity()` and `identityFor()`; nothing requires an
  identity provider (every test before it unchanged and green).
- **omniplay/edgegap and omniplay/steam**: their requests checked one by one on Symfony's
  `MockHttpClient` against answers written after Edgegap's OpenAPI specifications (v2 2026.08.05,
  v1) and the Steamworks Web API reference; neither called live.
- **Live, Docker** (Docker Desktop, engine 29.8.2, through `/var/run/docker.sock` mounted in the
  harness): a `php:8.4-cli` container started by `DockerHosting`, listed by `running()`, its
  environment holding `OMNIPLAY_TICKET_SECRET` and `OMNIPLAY_ROOM`, stopped and removed by Docker
  (`AutoRemove`); 1 test, 6 assertions, 10.2 s.
- **The providers inside the game server**, on the same `Tests/vectors.json` (copied, identical by
  `cmp`):
  - omniplay/node: `node --test` on Node 26.12, 35 tests green (5 sign vectors from the payload and
    from the JSON, 22 verify vectors, the heartbeat vector, heartbeats posted to an HTTP server of
    the test's process).
  - omniplay/dotnet: `dotnet test` on the .NET SDK 10.0.403, 36 tests green (the same vectors, the
    heartbeat client, `TicketGate`); the library built for netstandard2.1, warnings as errors.
  - omniplay/mirror and omniplay/netcode: compiled with omniplay/dotnet against Unity 6000.3's
    `UnityEngine.CoreModule` and stubs carrying the signatures of Mirror 93.0.0 and of
    com.unity.netcode.gameobjects 1.12.0 and 3.2.1: 0 errors, 0 warnings. Not imported in an editor.
  - omniplay/godot: **not run** (no Godot binary at hand); its tests run the same vectors.

Not verified:

- Edgegap live (no account): a deployment, its status, its listing, its stop; whether Edgegap
  takes `:` and `=` in a tag (its specification gives 40 characters at most, no character set);
  `current_status` values other than the specification's example (the provider reads `running`,
  `error` and the ports instead).
- Steam live (no publisher key, no game ticket): `AuthenticateUserTicket`'s response shape
  (`response.params`, `response.error`) is the one Steam's clients read, not printed in Steam's
  reference.

- A game server of any engine seated end to end (a client connecting with its ticket); a heartbeat
  sent by a provider inside the game server and answered by `Heartbeats` (the vectors prove the
  token is the one PHP makes).
- A Docker Engine over TCP or TLS (the requests are checked against recorded answers only).
- Concurrency: two `Seating::seat()` at once on one store may both occupy the last seat; the next
  heartbeat gives the true count. A store with atomic updates is the application's.
