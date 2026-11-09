---
title: The Docker harness
order: 70
---

# The Docker harness

`docker/` runs the core with every provider in PHP 8.4 (Alpine), nothing installed on the host, in
the image `omnisense/omniplay-harness`:

```sh
cd docker
echo 'OMNIPLAY_PLUGINS=../..' > .env      # the providers' checkouts beside the core
docker compose run --rm omniplay test     # every package's tests, recorded answers, no network
docker compose run --rm omniplay bare     # the bare script: what omniplay does and what PHP loaded
docker compose run --rm omniplay live     # the checks that need no key (Steam's store, GOG, itch.io pages)
docker compose run --rm omniplay phpstan  # PHPStan, highest level, each package
docker compose down -v                    # the harness's volume gone
```

## What it installs

At each start, `docker/harness/setup.php` writes the harness's `composer.json` and `phpunit.xml` in
the volume (`/harness`); the entrypoint runs `composer install` the first time and `composer
update` when the `composer.json` changed:

- the core from its checkout (`/omniplay/core`, a path repository, symlinked);
- each provider of `docker/harness/plugins.php` (steam, gog, itch, igdb, rawg) from a checkout
  under `OMNIPLAY_PLUGINS` when there is one - `docker/.env`, git-ignored - else from GitHub
  (`https://github.com/glitchr-omnisense/omniplay-<slug>.git`, branch 1.x);
- PHPUnit 11, PHPStan 2.1 and `symfony/http-client` for the tests; one suite per package.

The service uses Docker's default bridge network and one named volume, `omniplay-harness_harness`.

## The commands

| Command | Runs |
|---|---|
| `test` (the default) | `vendor/bin/phpunit --exclude-group live` |
| `live` | `vendor/bin/phpunit --group live`: Steam's store (Portal 2's record and price, a search), GOG's catalogue (a product, a search), an itch.io page |
| `bare` | `docker/harness/bin/bare`: a registry with the fixed provider (capabilities, a search, a game, platforms folded, a library, ownership) and the fixed hosting, two players seated (one on a server started for them) and their tickets checked, then `JsonClient` with no network (a record, a 404, a 500); the results and every class and file PHP loaded, as JSON |
| `phpstan` | PHPStan on the core and each provider |
| `composer ...`, `php ...`, `sh` | as they are, in `/harness` |

```
$ docker compose run --rm omniplay test
OK (44 tests, 245 assertions)
```

(2026-10-27, every provider from its checkout; `phpstan` the same day: no error in the six
packages). Some of what `bare` printed:

```
search ["Portal 2"]
platforms ["windows", "mac", "linux", "playstation"]
owns [true, false]
json-404 null
json-error "Example answered 500 for GET /games/3. Internal error"
```

## Running a script of your own

```sh
docker compose run --rm -v "$PWD/scratch:/scratch" omniplay php /scratch/game.php
```

with `require '/harness/vendor/autoload.php';` in the script. With no network at all:

```sh
docker run --rm --network none \
    -v omniplay-harness_harness:/harness:ro -v "$PWD/..:/omniplay/core:ro" -v "$PWD/../..:/omniplay/plugins:ro" \
    -v "$PWD/scratch:/scratch:ro" --entrypoint php omnisense/omniplay-harness /scratch/game.php
```

## Online play in the harness

`docker/`: PHP 8.4 (alpine) with Composer, the core from its checkout and each provider of
`docker/harness/plugins.php` from a checkout under `OMNIPLAY_PLUGINS` when there is one, from
GitHub (branch 1.x) otherwise.

```sh
cd docker
printf 'OMNIPLAY_PLUGINS=../..\nOMNIBASE_CORE=../../../omnibase/core\n' > .env   # git-ignored
docker compose run --rm omniplay test      # every package's tests
docker compose run --rm omniplay bare      # the plain-PHP script and what it loaded
docker compose run --rm omniplay phpstan   # PHPStan max on each package
docker compose run --rm -v /var/run/docker.sock:/var/run/docker.sock omniplay live   # the Docker provider against a real engine
docker compose down -v
```

`OMNIBASE_CORE`, an omnibase checkout, is mounted read-only for `OmnibaseParityTest`; without it
that test is skipped. The live run starts a container of an image the engine already has
(`php:8.4-cli`, or `OMNIPLAY_LIVE_IMAGE`), checks it, and stops it.
