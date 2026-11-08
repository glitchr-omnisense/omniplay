# Changelog

## Unreleased (1.x)

### Added
- `omnisense/omniplay`: online play for games on the platform side. Models (`Server`, `Service`,
  `Seat`, `Ticket`, `SeatRequest`, `Health`, `HeartbeatResult`), contracts by capability
  (`FleetInterface`, `SeatingInterface`, `TicketsInterface`, `HostingInterface`), the core's
  `Fleet`, `Seating`, `Tickets` and `Heartbeats`, `Registry`, `Config` and `ProviderFactory` as in
  the other omni families.
- `Ticket\TicketSigner`: the tickets' one format, `base64url(json) "." hex(hmac_sha256)`, written
  as omnibase's `Base\Service\Ticket\TicketSigner` writes it, without requiring omnibase;
  `Tests/vectors.json` (5 signed, 22 verified) and `bin/vectors.php`, which writes them.
  `OmnibaseParityTest` checks that both signers answer every vector the same. One difference,
  outside the vectors: an `exp` written as a string holds in omnibase (cast to an integer), not
  here (a number only, as the other languages read it).
- `Heartbeats`: a server's heartbeat, signed for the room `heartbeat:<server id>` so that a
  player's ticket never passes for one; a server the fleet does not know registers when it gives
  its address.
- `Storage\StoreInterface` and `InMemoryStore`; `Secret\SecretsInterface`, `EnvironmentSecrets`,
  `FixedSecrets`.
- `Http\JsonClient`, `Http\Data`, `Http\Redaction` from omnigame; `JsonClient` reads an empty body
  (Docker's 204 and 304) as empty data.
- `Testing\FixedHosting` and its factory, `RecordedHttpClient`, `BareTestCase`; the Docker
  harness (test, live, bare, phpstan).
- The player identity capability, optional: `IdentityInterface` (`platform()`, `verify(proof):
  Identity`), `Model\Identity` (platform, id, claims; `player()` is `<platform>:<id>`),
  `Exception\IdentityRefusedException` (a refusal that says why), `Capabilities::$identity`,
  `Registry::identity()` and `identityFor()`; a `SeatRequest` takes an `Identity` as its player.
  `Testing\FixedIdentity` and its factory (`fixed-identity`). docs/identity.md.
- `ProviderException::answered()` gives the HTTP status as the exception's code.
- The harness installs omniplay/edgegap and omniplay/steam beside omniplay/docker.
