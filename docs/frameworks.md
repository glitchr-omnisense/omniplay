---
title: Symfony and Laravel
order: 85
---

# Symfony and Laravel

No bridge yet: the classes are plain PHP, wired as services of the application's.

```yaml
# Symfony: config/services.yaml
services:
    Omniplay\Secret\SecretsInterface: '@Omniplay\Secret\EnvironmentSecrets'
    Omniplay\Secret\EnvironmentSecrets: ~
    Omniplay\Storage\StoreInterface: '@App\Omniplay\DoctrineStore'   # the application's own
    Omniplay\Fleet: { arguments: ['@Omniplay\Storage\StoreInterface'] }
    Omniplay\Tickets: { arguments: ['@Omniplay\Secret\SecretsInterface'] }
```

```php
// Laravel: a service provider's register()
$this->app->singleton(Fleet::class, fn ($app) => new Fleet($app->make(StoreInterface::class)));
$this->app->singleton(Heartbeats::class, fn ($app) => new Heartbeats($app->make(Fleet::class), new EnvironmentSecrets(), [new Service('game', secret: 'GAME_TICKET_SECRET')]));
Route::post('/omniplay/heartbeat', function (Request $request, Heartbeats $heartbeats) {
    $result = $heartbeats->receive((string) $request->header('Authorization'), $request->json()->all());

    return response()->json($result->toArray(), $result->status);
});
```

The wiring will live in omnibridge (`omnibridge/symfony`, `omnibridge/laravel`) when a site needs
it.
