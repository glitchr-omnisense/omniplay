---
title: Tickets
order: 30
---

# Tickets: the format

A ticket is a short-lived signed text a player presents to a server. The server checks it by itself
with the secret it shares with the application; it never calls the application back. The format is
the same in every language, so that a server written in anything can check what PHP signed.

```
ticket    = body "." signature
body      = base64url(payload)                      ; RFC 4648 section 5, no padding
signature = hex(hmac_sha256(key = secret, message = body))   ; 64 lower-case hex digits
payload   = a JSON object: "room" (a string), "exp" (Unix seconds), and the application's claims
```

The signature is over the **body as it stands**, the base64url text, not over the JSON: a verifier
never encodes anything again, and two languages that write JSON differently still agree.

## Signing

`Omniplay\Ticket\TicketSigner::sign($payload, $secret)` encodes the payload with PHP's
`json_encode()` and its default flags (slashes as `\/`, every character past ASCII as `\uXXXX`,
surrogate pairs for those past U+FFFF), then signs it. It refuses a payload without a non-empty
string `room` or an integer `exp`, and an empty secret. `signJson($json, $secret)` signs a JSON
text as given. `Tickets::mint()` builds the payload: the application's claims, then `room`, then
`exp` (now plus the service's ttl); `Seating` adds the player as `sub` and uses the server's id as
the room.

```php
TicketSigner::sign(['uid' => 2, 'room' => 'lobby', 'exp' => 2000000000], 'secret');
// eyJ1aWQiOjIsInJvb20iOiJsb2JieSIsImV4cCI6MjAwMDAwMDAwMH0.313fb4df1b3be1ffc941ae513ec9a3fcad3827593abcfb3545d9424b7b2aedfb
```

## Verifying

A verifier, given the ticket, the secret and the room it serves, answers the payload or nothing:

1. An empty secret verifies nothing.
2. The signature is what follows the **last** dot; no dot, nothing.
3. `hmac_sha256(secret, body)` in lower-case hex equals the signature, compared in constant time
   (a signature in capitals does not hold).
4. The body, base64url-decoded (padding restored), is JSON whose top level is an object.
5. `room` is a string equal to the room served (a number `5` is not the room `"5"`).
6. `exp` is a JSON number; its integer part is not before now (an expiry equal to now still
   holds). A string, a boolean or nothing there: refused.
7. The payload is answered whole: `room`, `exp` and every claim.

`TicketSigner::verify($ticket, $secret, $room, $now = null)` is that in PHP.

## The vectors

`Tests/vectors.json` (written by `bin/vectors.php`): `sign` holds 5 payloads with their secret,
their JSON as PHP writes it and the ticket expected; `verify` holds 22 tickets with the secret and
room they are checked with, whether they hold, and the payload when they do. Valid tickets expire
at 4102444800 (2100-01-01), expired ones at 1000000000 (2001-09-09): check them against the real
clock. They cover a player's seat, slashes and characters past ASCII (`"Zoë 🎮"`), nested claims,
a heartbeat token, an expiry with a fraction, and every refusal above (expired, another room,
another secret, an empty secret, the payload changed, the signature changed or in capitals or
empty, no dot, an extra dot, no expiry, a boolean expiry, a numeric room, no room, a list, a body
that is not base64url JSON, a player's ticket presented as a heartbeat).

A verifier in another language passes when, for every `verify` vector, it answers the payload
(deep-equal) or nothing as the vector says; a signer, when `signJson(json, secret)` gives each
`sign` vector's ticket.

| Checked against the vectors | Language | Verify | Sign |
|---|---|---|---|
| `Omniplay\Ticket\TicketSigner` | PHP | 22 | 5 (from the payload and from the JSON) |
| omnibase's `Base\Service\Ticket\TicketSigner` (`OmnibaseParityTest`) | PHP | 22, the same answers | 5 |
| omniplay/node | JavaScript | 22 | 5 (from the payload, PHP's escaping, and from the JSON) |
| omniplay/dotnet (and through it omniplay/mirror and omniplay/netcode) | C# | 22 | 5 (from the payload, PHP's escaping, and from the JSON) |
| omniplay/godot | GDScript | 22 | 5 (from the payload and from the JSON) - not run: no Godot binary where it was written |

## The secret

One secret per `Service`, named by reference (`Service::$secret`, an environment variable's name by
default) and read through a `SecretsInterface` when a ticket is minted or a server started. A
hosting provider hands it to the server it starts (Docker: in the container's environment,
`OMNIPLAY_TICKET_SECRET` unless configured otherwise). Whoever holds it can mint tickets: keep it
off the client, out of images and out of logs. Rotating it means restarting the servers with the
new one; tickets live a minute by default, so the old ones die with them.

## omnibase

omnisense/omnibase signs tickets in this format too (`Base\Service\Ticket\TicketSigner`, for its
collaboration relay). Omniplay does not require it: the two are kept equal by
`OmnibaseParityTest`, which loads omnibase's class when it is installed beside omniplay or named in
`OMNIBASE_TICKET_SIGNER`. They differ in one case no signer writes: an `exp` written as a string
(`"4102444800"`) holds in omnibase, which casts it, and not here.
