---
title: Errors
order: 50
---

# Errors

Every exception Omniplay throws implements `Omniplay\Exception\OmniplayException` (a `\Throwable`):

| Exception | Extends | When |
|---|---|---|
| `InvalidArgumentException` | `\InvalidArgumentException` | a search without words, a limit outside 1-50, an empty identifier, a register name that is not one |
| `InvalidConfigException` | `\InvalidArgumentException` | a provider not configured, a factory not installed, a required option missing; Steam's or itch.io's players without a `key`; a Steam player that is not a 64-bit Steam ID; itch.io's library of anyone but `me` |
| `NotSupportedException` | `\LogicException` | a contract the provider lacks (`Registry::search('itch')`), an identifier it does not resolve. Thrown before any call |
| `ProviderException` | `\RuntimeException` | a status of 400 or more but 404, a body that is not JSON, Twitch giving no token |
| `UnreachableException` | `\RuntimeException` | a network error, a timeout |

Not an exception: a game the store does not know - a 404 included - is `null`; a search with no
match `[]`; a player who does not own the game `false`.

## The messages

Seen in the [harness](harness.md) on 2026-10-27, the network ones in a container with no network
(those three as they read since 2026-10-29: the address the HTTP client quotes left out, and IGDB's
token asked with the credentials in the body):

```
steam library    UnreachableException: Steam could not be reached for GET /IPlayerService/GetOwnedGames/v1/: Could not resolve host: api.steampowered.com (Could not contact DNS servers).
rawg network     UnreachableException: RAWG could not be reached for GET /api/games/portal-2: Could not resolve host: api.rawg.io (Could not contact DNS servers).
igdb network     UnreachableException: IGDB could not be reached for POST /oauth2/token: Could not resolve host: id.twitch.tv (Could not contact DNS servers).
steam no key     InvalidConfigException: A player's Steam library needs the "key" option (a Steam Web API key).
steam player     InvalidConfigException: "gabe" is not a 64-bit Steam ID.
steam gog ref    NotSupportedException: Steam does not find a game by its gog identifier.
steam unknown    NULL
steam 429        ProviderException: Steam answered 429 for GET /api/appdetails. Too Many Requests
itch library     InvalidConfigException: itch.io lets a key read the library of its own account only: ask for "me".
itch search      NotSupportedException: The "i" provider (Omniplay\Itch\ItchProvider) does not search.
igdb token       ProviderException: IGDB answered 400 for POST /oauth2/token. {"status":400,"message":"invalid client secret"}
rawg no key      InvalidConfigException: The "rawg" provider needs: api_key.
rawg 401         ProviderException: RAWG answered 401 for GET /api/games. {"error":"The key parameter is not provided"}
query limit      InvalidArgumentException: A search answers 1 to 50 games.
ref empty        InvalidArgumentException: A game is named by a non-empty identifier.
```

A `ProviderException` names the provider, the status, the method and the path - never the query -
then the answer's text (tags stripped, 300 characters at most); its code is 0.

An `UnreachableException` names the method and the path too, then the HTTP client's reason without
the address it quotes (`JsonClient::reason()`): Steam's Web API key and RAWG's key, which travel in
the query, stay out of it. Its previous exception is a copy of the HTTP client's
(`Http\Redaction::copy()`): the same class and code, the message with the URL's key parameters
masked (`key=***`), its own previous copied the same way. Neither keeps its trace's arguments,
where the request's query, headers and body stood (IGDB's Twitch `client_secret`): a log that
writes the whole chain, arguments included (`zend.exception_ignore_args` off), holds no key. Nor does an error answered (a refused key, nothing there, a server error, a body
that is not JSON): every exception `JsonClient::request()` throws leaves its trace's arguments out.

## What to catch where

| Where | Catch |
|---|---|
| a page showing a store's record | `OmniplayException`: show what the application kept, log the message privately |
| a check before granting access (ownership) | `UnreachableException` and `ProviderException`: do not grant on an error; ask again later |
| a batch refresh of prices | a `ProviderException` for a 429 or 5xx: wait and retry, at a gentler pace |
| building the registry, a deploy | `InvalidConfigException`: a configuration error, to fix |
| a feature a provider may lack | check `capabilities()->contracts()` and `resolves()` first |

## Online play

Every exception implements `Omniplay\Exception\OmniplayException`.

| Exception | When | Message, as seen |
|---|---|---|
| `InvalidArgumentException` | a value nothing can hold: an empty id, a capacity of 0, a ticket without room or expiry, claims named `room` or `exp`, a payload that is not JSON | `A ticket names its room: "room", a non-empty string.` |
| `InvalidConfigException` | a provider not configured, a factory not installed, an option missing, a secret not found | `No "docker" factory for the "local" game provider; installed: fixed-hosting.`, `The "docker" provider needs: services.`, `No secret in the environment variable "GAME_TICKET_SECRET".` |
| `NotSupportedException` | a provider asked for what it does not do | `The "other" provider (...) does not start servers.`, `The "local" provider (...) does not verify players.`, `Docker starts no server of the "relay" service.` |
| `ProviderException` | the provider answered with an error, or not JSON; its code is the HTTP status | `Docker answered 500 for POST /v1.44/containers/<id>/start. {"message": "...port is already allocated"}` |
| `IdentityRefusedException` | a platform refused a player's proof, or the proof is not one | `Steam refused the ticket: Invalid ticket (error 101).` |
| `UnreachableException` | the provider could not be reached | `Docker could not be reached for GET /v1.44/containers/json: <the client's reason, without the address>.` |

A ticket or a heartbeat that does not hold is no exception: `verify()` answers null,
`Heartbeats::receive()` a `HeartbeatResult` with its status.

### The messages

As in omniplay, `JsonClient` names the method and the path only, never the query, and the
exception chain keeps no trace arguments (`Http\Redaction`): a request's options - a Docker
container's environment, which holds the server's secret - are in no trace.
