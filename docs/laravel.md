---
title: Laravel
order: 16
---

# Laravel

omnibridge/laravel, the bridge that hosts omnibase in Laravel, has no Omniplay provider yet. The
registry is plain PHP: a service provider of the application's builds it once, from a config file,
with a `symfony/http-client` client (every provider requires that package; Laravel's own HTTP
client is Guzzle's, which the factories do not take).

```php
// config/omniplay.php
return [
    'providers' => [
        'steam' => ['factory' => 'steam', 'options' => [
            'key' => env('STEAM_WEB_API_KEY'),       // null: the store only
            'language' => 'fr',
            'country' => 'FR',
        ]],
        'rawg' => ['factory' => 'rawg', 'options' => ['api_key' => env('RAWG_KEY')]],
    ],
];
```

```php
// app/Providers/OmniplayServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Omniplay\Rawg\RawgFactory;
use Omniplay\Registry;
use Omniplay\Steam\SteamFactory;
use Symfony\Component\HttpClient\HttpClient;

final class OmniplayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Registry::class, function (): Registry {
            $http = HttpClient::create(['timeout' => 20]);

            return new Registry([
                new SteamFactory($http),
                new RawgFactory($http),
            ], config('omniplay.providers'));
        });
    }
}
```

Register the provider (`bootstrap/providers.php` on Laravel 11 and later), then inject the
registry where it is needed:

```php
use Illuminate\Support\Facades\Cache;
use Omniplay\Model\GameRef;
use Omniplay\Registry;

final class GameController
{
    public function show(Registry $stores, int $app)
    {
        $game = Cache::remember('steam.'.$app, now()->addHours(6), fn () => $stores->catalogue('steam')->game(GameRef::steam($app))?->toArray());

        return view('games.show', ['game' => $game]);
    }
}
```

List in the provider every factory the application uses: a hand-built registry knows only the
factories it is given. Configuration caching (`php artisan config:cache`) keeps working: the
options are plain arrays read through `config()`. This page was written from the registry's API;
it was not run in a Laravel application.

## Online play

The fleet and the heartbeats' route, in a service provider:

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
