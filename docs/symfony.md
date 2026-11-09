---
title: Symfony
order: 15
---

# Symfony

There is no Omniplay bundle, and omnibridge/symfony has no Omniplay wiring yet: a Symfony
application declares the registry as a service of its own. Every factory takes the HTTP client as
its one constructor argument (`?Symfony\Contracts\HttpClient\HttpClientInterface`), which
autowiring fills with the application's `http_client` service.

## The registry as a service

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true

    Omniplay\Steam\SteamFactory: ~
    Omniplay\Gog\GogFactory: ~
    Omniplay\Igdb\IgdbFactory: ~

    Omniplay\Registry:
        arguments:
            $factories:
                - '@Omniplay\Steam\SteamFactory'
                - '@Omniplay\Gog\GogFactory'
                - '@Omniplay\Igdb\IgdbFactory'
            $config:
                steam:
                    factory: steam
                    options:
                        key: '%env(default::STEAM_WEB_API_KEY)%'   # only for players' libraries
                        language: fr
                        country: FR
                gog:
                    factory: gog
                    options: { locale: fr-FR, country: FR, currency: EUR }
                igdb:
                    factory: igdb
                    options:
                        client_id: '%env(TWITCH_CLIENT_ID)%'
                        client_secret: '%env(TWITCH_CLIENT_SECRET)%'
```

```sh
# .env.local, or bin/console secrets:set
STEAM_WEB_API_KEY=...
TWITCH_CLIENT_ID=...
TWITCH_CLIENT_SECRET=...
```

The order of `$config` is the order `Registry::game()` asks the providers in when no name is given.
Steam's `key` is optional: empty, the provider reads the store and refuses players' libraries (an
empty option is the same as none).

A tag gathers the factories instead of listing them:

```yaml
services:
    _instanceof:
        Omniplay\ProviderFactoryInterface:
            tags: ['omniplay.factory']

    Omniplay\Registry:
        arguments:
            $factories: !tagged_iterator omniplay.factory
            $config: '%omniplay.providers%'
```

## Using it

```php
use Omniplay\Model\GameRef;
use Omniplay\Registry;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class StorePanel
{
    public function __construct(private readonly Registry $stores, private readonly CacheInterface $cache)
    {
    }

    /** @return ?array<string, mixed> */
    public function steam(int $app): ?array
    {
        return $this->cache->get('steam.'.$app.'.fr', function (ItemInterface $item) use ($app): ?array {
            $item->expiresAfter(6 * 3600);        // prices change with sales

            return $this->stores->catalogue('steam')->game(GameRef::steam($app), 'fr', 'FR')?->toArray();
        });
    }
}
```

Nothing is built when the container compiles: a provider is built, and its required options
checked, the first time the registry is asked for it, then kept. Records are not cached by
Omniplay: the stores' endpoints are not made for heavy traffic (Steam's and GOG's storefront
endpoints are undocumented), and the application's cache keeps them.

There is no command, event, Twig function or route. Show each provider's
`capabilities()->attribution` beside its records, as its terms ask: `Game::$source` names the
provider a record came from.

This page was written from the classes' constructors; it was not run in a Symfony kernel.

## Online play

The fleet, the tickets and the heartbeats are plain classes too, wired as services of the application's:

```yaml
# Symfony: config/services.yaml
services:
    Omniplay\Secret\SecretsInterface: '@Omniplay\Secret\EnvironmentSecrets'
    Omniplay\Secret\EnvironmentSecrets: ~
    Omniplay\Storage\StoreInterface: '@App\Omniplay\DoctrineStore'   # the application's own
    Omniplay\Fleet: { arguments: ['@Omniplay\Storage\StoreInterface'] }
    Omniplay\Tickets: { arguments: ['@Omniplay\Secret\SecretsInterface'] }
```
