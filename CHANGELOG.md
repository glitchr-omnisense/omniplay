# Changelog

## Unreleased (1.x)

### Changed
- 2026-11-10: on omnisense/contracts, the kernel every family builds on. Config, ProviderInterface,
  ProviderFactoryInterface, ProviderFactory, Registry, Http\JsonClient, Data, Redaction,
  Testing\RecordedHttpClient, RecordedResponse and BareTestCase keep their names and their API,
  each a thin subclass of the kernel's (`bin/load-all.php` moved there); JsonClient still reads an
  empty body as an empty Data. Usage policies: `Capabilities::policy()` (given, or made of the
  older fields, now deprecated and kept as given; `toArray()` unchanged), every call through
  `search()`, `catalogue()`, `library()`, `ownership()` guarded (Policy\Guarded*: a rate limit
  reached, a 429 and its Retry-After, a RateLimit field with nothing left), `hosting()`,
  `hostingFor()`, `identity()` and `identityFor()` guarded when the policy limits their calls, a
  site's configuration tightening a policy (`policy`, `usage`), `Registry::policy()`, `policies()`,
  `guard()`, a shared limiter as the registry's third argument,
  Exception\UsageLimitReachedException. The harness installs omnisense/contracts from its checkout
  (OMNISENSE_CONTRACTS) and runs its tests too. docs/policies.md.
- One family: omnisense/omnigame merged into omnisense/omniplay on 2026-11-09, its history kept
  (the merge of omnigame's `1.x` at 6010da9). `Omnigame\` is now `Omniplay\`; `omnigame/gog`,
  `omnigame/igdb`, `omnigame/itch` and `omnigame/rawg` are `omniplay/<provider>`, and
  `omnigame/steam`'s catalogue is in `omniplay/steam`, beside its identity.
- `Capabilities` holds both families' fields: online play's (hosting, services, regions, where,
  identity) and the stores' (search, catalogue, library, ownership, lookupBy, prices, player,
  attribution, terms, keyless); `contracts()` lists the six contracts, `toArray()` every field.
- One `Registry`, `Config`, `ProviderFactory` and `ProviderInterface` for every contract:
  `search()`, `catalogue()`, `library()`, `ownership()` and `game()` beside `hosting()`,
  `hostingFor()`, `identity()` and `identityFor()`; an unknown provider or factory is a "game
  provider"'s (was a "hosting provider"'s).
- `Testing\FixedHostingFactory` is named `fixed-hosting` (was `fixed`, now `FixedProviderFactory`'s,
  the stores' fixed provider), beside `fixed-identity`.
- `NotSupportedException::ref()` beside `::service()`.
- The harness installs omniplay/gog, omniplay/igdb, omniplay/itch and omniplay/rawg beside
  omniplay/docker, omniplay/edgegap and omniplay/steam.

### Added (the stores, from omnisense/omnigame)
- The stores' contracts, read only: `SearchInterface`, `CatalogueInterface`, `LibraryInterface`,
  `OwnershipInterface`; the models `Game`, `GameRef`, `Scheme`, `Identifiers`, `Query`, `Platform`
  (`fromLabels()`), `Price`, `Image`, `ImageKind`, `Rating`, `OwnedGame`; `Registry::game()`, asking
  in turn each provider that resolves an identifier; `Testing\FixedProvider` and its factory
  (`fixed`). docs/ (usage-search, usage-catalogue, usage-players, symfony, laravel; models,
  configuration, errors, installation, testing, harness, providers and verified with the stores'
  sections).
- `Omniplay\Http\Redaction`: a request's secrets (`secrets()`), a text without them (`text()`),
  a failure's copy to wrap in its place (`copy()`), a trace without arguments
  (`withoutArguments()`).
- `ext-mbstring` in `composer.json`'s requirements: `ProviderException` shortens error bodies with
  `mb_substr()`, which a PHP built without mbstring did not have (no test: a requirement).
- `Omniplay\Http\JsonClient::reason()`: an HTTP client's message without the addresses it quotes.

### Fixed (in omnisense/omnigame, before the merge)
- No exception `JsonClient::request()` throws keeps its trace's arguments: an error answered (a refused key,
  nothing there, a server error, a body that is not JSON) kept the request's options - the query,
  headers and token with the key - in its trace (`zend.exception_ignore_args` off), as the
  `UnreachableException` did before its fix.
- An `UnreachableException`'s chain no longer holds the key. Its previous exception was the HTTP
  client's own, whose message quotes the whole URL (Steam's and RAWG's `key=`), and every trace
  kept the arguments of the calls under it, the request's query, headers and body (IGDB's Twitch
  `client_secret`) among them. The previous is now a copy of the client's exception
  (`Http\Redaction::copy()`: the same class and code, the key's parameters, the URL's user part
  and the request's secrets masked as `***`), and no trace of the chain keeps its arguments
  (`Redaction::withoutArguments()`).
- `Registry::library()` and `::ownership()` give a provider only when its `capabilities()` says it
  can: Steam and itch.io without a `key` are a `NotSupportedException` there, where the registry
  returned them and their `library()` then threw `InvalidConfigException`.
- An `UnreachableException` no longer quotes the URL called: as its docblock promised, the message
  names the method and the path, then the HTTP client's reason without the address it quotes.
  Symfony's transport messages end with the whole URL, so Steam's and RAWG's `key=` and IGDB's
  Twitch `client_secret=` stood in the message.

### Added (online play)
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
