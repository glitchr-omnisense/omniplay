---
title: Verified, and not
order: 90
---

# Verified, and not

On 2026-11-08:

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
