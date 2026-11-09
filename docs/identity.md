---
title: Player identity
order: 45
---

# Player identity

A seat names its player by a string the application chooses. When the player comes from a
platform that can vouch for them - Steam, a console's network, a store - the application can have
that platform verify the player's proof first, and seat the player under the id the platform gave.
That is the identity capability. It is optional: nothing in omniplay requires an identity
provider, and a `SeatRequest` takes a plain string as before.

It runs **on the platform**, in the application, as hosting does: the game client gets a proof
from the platform's SDK (a Steam session ticket), sends it to the application with its request to
play, and the application asks the platform whether it holds. The game server is not involved: it
reads the player's id from the omniplay ticket, as it always does.

## The contract

```php
interface IdentityInterface
{
    public function platform(): string;               // "steam"
    public function verify(string $proof): Identity;  // or IdentityRefusedException, saying why
}
```

`verify()` answers the player the platform vouches for, or throws:

| Exception | When |
|---|---|
| `IdentityRefusedException` | the platform refused the proof (`Steam refused the ticket: Invalid ticket (error 101).`), or the proof is not one and the platform was not asked |
| `ProviderException` | the platform answered with an error status, or with no verdict |
| `UnreachableException` | the platform could not be reached |

A refusal is never silent and never a null: a player whose proof does not hold is not seated
under a guessed id. Whether to seat them anyway, under a guest id, is the application's choice.

## Identity

`new Identity($platform, $id, $claims = [])`: the platform (lower case: `steam`), the player's id
there (a SteamID64), and what the platform said besides (`owner`, `vac_banned`... for Steam).
`player()` - `steam:76561197960287930` - is the player's id across platforms; `toArray()` gives
`player`, `platform`, `id`, `claims`.

## In the registry

A provider that verifies players says so in its capabilities (`identity: true`; `contracts()`
lists `identity`). The registry finds it as it finds a hosting provider:

```php
use Omniplay\Model\SeatRequest;
use Omniplay\Registry;
use Omniplay\Steam\SteamFactory;

$providers = new Registry([new SteamFactory(), new DockerFactory()], [
    'steam' => ['factory' => 'steam', 'options' => ['appid' => 480, 'publisher_key' => 'STEAM_PUBLISHER_KEY', 'identity' => 'omniplay']],
    'local' => ['factory' => 'docker', 'options' => [/* ... */]],
]);

$player = $providers->identityFor('steam')?->verify($request->get('ticket'));   // Identity, or a refusal
$seat = $seating->seat($game, new SeatRequest($player, 'eu'));
$seat?->toArray();   // the ticket's "sub" is "steam:76561197960287930"
```

- `identity($name)`: the provider configured under that name, or a `NotSupportedException` when
  it verifies no player.
- `identityFor($platform, ...$names)`: the first provider (in the configuration's order, or the
  order named) that verifies that platform's players; null when none does - the application then
  names its players itself.

## In a seat

`new SeatRequest($identity, $region, $tags, $claims)`: the player's id is `$identity->player()`,
and `$request->identity` keeps the `Identity`. `Seating` puts the player's id in the ticket's
`sub`, as for any player; the platform's claims stay with the application unless it passes some as
the request's `$claims`. `Tickets` is unchanged.

## Providers

| Package | Platform | Proof |
|---|---|---|
| [omniplay/steam](https://github.com/glitchr-omnisense/omniplay-steam) | `steam` | a session ticket from `GetAuthTicketForWebApi`, hex-encoded, verified with `ISteamUserAuth/AuthenticateUserTicket` |
| `Testing\FixedIdentity` (factory `fixed-identity`) | `test`, or the one given | the proofs it was given |

A provider of another platform implements `ProviderInterface` and `IdentityInterface`, says
`identity: true` in its capabilities, reads its platform key by reference (`SecretsInterface`),
and refuses aloud ([Writing a provider](providers.md)).
