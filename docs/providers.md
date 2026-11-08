---
title: Writing a provider
order: 80
---

# Writing a provider

A provider on the platform starts and stops servers, or verifies players for a platform. It is a package `omniplay/<name>`,
namespace `Omniplay\<Name>\`, requiring `omnisense/omniplay` (and `symfony/http-client` when it
calls an API), written as omnigame's are:

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
