---
title: Verified, and not
order: 90
---

# Verified, and not

On 2026-11-08:

- **The harness** (PHP 8.4.27): 67 tests green on the core and omniplay/docker, PHPStan at its
  highest level on both with an empty baseline. Among them: every vector of `Tests/vectors.json`
  (5 signed, 22 verified) signed and verified by `TicketSigner`, and answered the same by
  omnibase's `Base\Service\Ticket\TicketSigner` (`OmnibaseParityTest`, omnibase mounted read-only);
  `BareTest` (nothing loaded but omniplay and the HTTP client contracts).
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

- A game server of any engine seated end to end (a client connecting with its ticket); a heartbeat
  sent by a provider inside the game server and answered by `Heartbeats` (the vectors prove the
  token is the one PHP makes).
- A Docker Engine over TCP or TLS (the requests are checked against recorded answers only).
- Concurrency: two `Seating::seat()` at once on one store may both occupy the last seat; the next
  heartbeat gives the true count. A store with atomic updates is the application's.
