---
title: Errors
order: 60
---

# Errors

Every exception implements `Omniplay\Exception\OmniplayException`.

| Exception | When | Message, as seen |
|---|---|---|
| `InvalidArgumentException` | a value nothing can hold: an empty id, a capacity of 0, a ticket without room or expiry, claims named `room` or `exp`, a payload that is not JSON | `A ticket names its room: "room", a non-empty string.` |
| `InvalidConfigException` | a provider not configured, a factory not installed, an option missing, a secret not found | `No "docker" factory for the "local" hosting provider; installed: fixed.`, `The "docker" provider needs: services.`, `No secret in the environment variable "GAME_TICKET_SECRET".` |
| `NotSupportedException` | a provider asked for what it does not do | `The "other" provider (...) does not start servers.`, `Docker starts no server of the "relay" service.` |
| `ProviderException` | the provider answered with an error, or not JSON | `Docker answered 500 for POST /v1.44/containers/<id>/start. {"message": "...port is already allocated"}` |
| `UnreachableException` | the provider could not be reached | `Docker could not be reached for GET /v1.44/containers/json: <the client's reason, without the address>.` |

A ticket or a heartbeat that does not hold is no exception: `verify()` answers null,
`Heartbeats::receive()` a `HeartbeatResult` with its status.

## The messages

As in omnigame, `JsonClient` names the method and the path only, never the query, and the
exception chain keeps no trace arguments (`Http\Redaction`): a request's options - a Docker
container's environment, which holds the server's secret - are in no trace.
