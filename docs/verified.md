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
- **The providers inside the game server**, on the same vectors: see each one's README (Node.js, .NET, GDScript).

Not verified:

- A game server of any engine seated end to end (a client connecting with its ticket).
- A Docker Engine over TCP or TLS (the requests are checked against recorded answers only).
- Concurrency: two `Seating::seat()` at once on one store may both occupy the last seat; the next
  heartbeat gives the true count. A store with atomic updates is the application's.
