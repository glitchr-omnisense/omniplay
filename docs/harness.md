---
title: The harness
order: 75
---

# The harness

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
