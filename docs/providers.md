---
title: Writing a provider
order: 80
---

# Writing a provider

A provider is a package `omniplay/<name>`, namespace `Omniplay\<Name>\`, requiring
`omnisense/omniplay` and `symfony/http-client`, written as Omnifilm's are
([Omnifilm's guide](https://github.com/glitchr-omnisense/omnifilm/blob/1.x/docs/providers.md) has a
skeleton that runs; replace `Work` with `Game`, `WorkRef` with `GameRef`):

1. **The factory** extends `ProviderFactory` (`populate()`: `omniplay.factory_name`,
   `omniplay.factory_title`, `omniplay.required_options`, the defaults; `build()`: the provider),
   its constructor taking `?HttpClientInterface $http = null` and falling back on
   `HttpClient::create()`.
2. **The provider** implements `ProviderInterface` and the capability interfaces it has, with
   honest `capabilities()`: a library or ownership offered only when the key it needs is
   configured, `player` saying what a player is, `lookupBy` the schemes `game()` resolves, the
   attribution and the terms' URLs. `game()` throws `NotSupportedException::ref()` for a scheme it
   does not resolve and returns `null` for a game it does not know.
3. **Calls** go through `Http\JsonClient`, answers are read with `Http\Data`. A key goes in a
   header (or a body) when the store accepts one: messages leave the query out
   ([Errors](errors.md#the-messages)), and so does the exception chain, but the HTTP client's own
   logs do not. A provider that calls its own library wraps its failure as `JsonClient` does:
   `Redaction::withoutArguments(new UnreachableException(..., 0, Redaction::copy($e,
   Redaction::secrets($url, $options))))`.
4. **The record**: prices in minor units with their ISO 4217 currency; platforms through
   `Platform::fromLabels()`; HTML stripped from summaries; the identifiers of every other store the
   source lists; what it does not give left empty.
5. **Tests**: recorded answers (captured when no key is needed, written after the documentation
   otherwise, and said so), a `BareTest` extending `Testing\BareTestCase`, a `LiveTest` in the
   group `live` for what needs no key. Then a line in `docker/harness/plugins.php`.
6. **Read only**: no purchase, no key granted, nothing written.

## The providers' terms, in short

| Provider | Attribution | Limits and use |
|---|---|---|
| steam | Valve's trademarks; no implied endorsement; links to Valve not "nofollow" | Web API: 100,000 calls a day; Steam data about a player only as that player asks; a privacy policy for what is kept. The store's endpoints are not documented by Valve: a gentle pace, answers kept |
| gog | - | no published API terms: the store's own endpoints, a gentle pace, answers kept |
| itch | - | the server-side API's scopes; an e-mail must be verified before an ownership lookup by e-mail |
| igdb | IGDB.com | Twitch's developer agreement; 4 requests a second |
| rawg | an active hyperlink to RAWG on every page that shows its data | free up to 20,000 requests a month; commercial use free under 100,000 monthly active users |

## Writing a hosting or identity provider, or a server-side one

A provider on the platform starts and stops servers, or verifies players for a platform. It is a package `omniplay/<name>`,
namespace `Omniplay\<Name>\`, requiring `omnisense/omniplay` (and `symfony/http-client` when it
calls an API), written as omniplay's are:

1. **The factory** extends `ProviderFactory` (`populate()`: `omniplay.factory_name`,
   `omniplay.factory_title`, `omniplay.required_options`, the defaults; `build()`: the provider),
   its constructor taking `?HttpClientInterface $http = null`.
2. **The provider** implements `ProviderInterface` and `HostingInterface`, with honest
   `capabilities()`: the services it has a configuration for, its regions. `start()` refuses
   (`NotSupportedException::service()`) what it does not start; it hands the server its secret and
   the variables of [Configuration](configuration.md#what-a-server-is-handed), and returns the
   server Starting, its `provider` and `instance` set, the secret in nothing it returns. `stop()`
   is quiet about a server already gone. `running()` rebuilds servers from what the platform keeps
   of them (Docker's labels).
3. **Calls** go through `Http\JsonClient`; a failure of its own wrapped as `JsonClient` does.
4. **Tests**: recorded answers written after the API's reference (and said so), a `BareTest`, a
   `LiveTest` in the group `live` when there is an account to run it with. Then a line in
   `docker/harness/plugins.php`.

A provider of player identity (omniplay/steam) implements `ProviderInterface` and
`IdentityInterface` instead: `capabilities()` says `identity: true`, `platform()` names the
platform, `verify()` answers an `Identity` or throws an `IdentityRefusedException` that quotes the
platform's reason - never null, never a guessed id. The platform's key is an option holding its
reference, read through a `SecretsInterface` at each call (the factory takes one, the environment
by default), so that the key is in no option, no exception and nothing returned. See
[Player identity](identity.md).

The providers that run inside the game server (omniplay/dotnet, /mirror, /netcode, /godot,
/node) implement no PHP contract: they verify tickets as [Tickets](tickets.md) says, send heartbeats
as [the heartbeat](heartbeat.md) says, and are checked against `Tests/vectors.json`.
