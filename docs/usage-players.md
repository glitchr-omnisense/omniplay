---
title: "Players: libraries and ownership"
order: 33
---

# Players: libraries and ownership

`LibraryInterface::library(string $player): array` lists a player's games (`OwnedGame`: identifiers,
title, playtime in minutes, last played); `OwnershipInterface::owns(string $player, GameRef $game):
bool` says whether a player owns a game. Two providers answer them, each with a key:

| | Player | Library | Ownership |
|---|---|---|---|
| steam (`key`: a Steam Web API key) | a 64-bit Steam ID, 17 digits (`76561197960287930`) | `IPlayerService/GetOwnedGames` with app info and free games played: playtime and last played; a private profile reads as `[]` | the library, searched for the app number (one call per check) |
| itch (`key`: a server-side API key) | an itch.io user id, a verified e-mail address, or a download key; `me` for the library | the key's own account only (`/profile/owned-keys`, pages of 500, 20 at most): identifiers and titles, no playtime | `/games/{id}/download_keys?user_id=...` (or `email=`, `download_key=`) on one of your games; a page ref is turned into its number from the page's `data.json` first |

```php
use Omniplay\Model\GameRef;

$steam = $stores->library('steam');
foreach ($steam->library('76561197960287930') as $owned) {
    printf("%s: %d h\n", $owned->title, intdiv($owned->playtime ?? 0, 60));
}

if ($stores->ownership('itch')->owns((string) $itchUserId, GameRef::itch('my-studio/my-game'))) {
    // grant the role, the download, the forum access
}
```

On the answer Steam's tests use (written after the Web API's documentation), a library reads:

```
  {"identifiers":{"steam":"620"},"title":"Portal 2","playtime":1250,"lastPlayed":"2026-01-02T12:00:00+00:00"}
```

(in the [harness](harness.md), 2026-10-27; no Steam key was used)

## Players' data

A player's library is that player's data. Valve's Web API terms allow it to be read only as that
player asks, and ask for a privacy policy saying what is kept; on itch.io, an e-mail address must
have been verified (by the application) before ownership is asked by it - anyone could type
another's. Prefer the identifier the player signed in with (Steam's OpenID, itch.io's OAuth).

## Without a key, with a wrong player

- No `key`: `capabilities()->library` and `->ownership` are `false`, but the provider still
  implements both interfaces ([Models](models.md#capabilities)): `library()` throws
  `InvalidConfigException` (`A player's Steam library needs the "key" option (a Steam Web API
  key).`).
- A Steam player that is not 17 digits: `InvalidConfigException` (`"gabe" is not a 64-bit Steam
  ID.`) before any call.
- itch.io's library of anyone but `me`: `InvalidConfigException` (`itch.io lets a key read the
  library of its own account only: ask for "me".`).
- itch.io's answer "no download key found" (or "invalid download key") is `false`, whatever its
  status; an unknown game page is `false`.
- A game of another scheme: `NotSupportedException` (`Steam does not find a game by its gog
  identifier.`).

GOG, IGDB and RAWG have no players: GOG offers no way for a third party to read a player's games.
