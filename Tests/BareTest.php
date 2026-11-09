<?php

namespace Omniplay\Tests;

use Omniplay\Http\Data;
use Omniplay\Registry;
use Omniplay\Testing\BareTestCase;

/**
 * omnisense/omniplay with no framework: the harness's bare script searches,
 * reads a game, a library and ownership, seats two players (one on a server
 * started for them), checks their tickets as a server does, and makes a
 * provider's JSON call, in a PHP process of its own - nothing may load from
 * outside the package but Symfony's HTTP client contracts (interfaces
 * only). Omnibase (Base\) is among what may not load.
 */
final class BareTest extends BareTestCase
{
    protected static function entryClass(): string
    {
        return Registry::class;
    }

    protected static function requiredPackages(): array
    {
        return ['symfony/http-client-contracts'];
    }

    public function testGamesReadAndPlayersSeatedByHandLoadNothingButOmniplayAndWhatItRequires(): void
    {
        $report = Data::of(self::php([__DIR__.'/../docker/harness/bin/bare']));

        self::assertSame(['search', 'catalogue', 'library', 'ownership'], $report->get('capabilities.contracts'));
        self::assertSame(['Portal 2'], $report->get('search'));
        self::assertSame('The Witcher: Enhanced Edition', $report->get('game.title'));
        self::assertSame(['amount' => 119, 'currency' => 'EUR', 'initial' => 799, 'discount' => 85], $report->get('game.price'));
        self::assertSame(['windows', 'mac', 'linux', 'playstation'], $report->get('platforms'));
        self::assertSame([['identifiers' => ['steam' => '620'], 'title' => 'Portal 2', 'playtime' => 1250, 'lastPlayed' => null]], $report->get('library'));
        self::assertSame([true, false], $report->get('owns'));
        self::assertSame(['hosting'], $report->get('hosting.contracts'));
        self::assertSame([['eu-1', '203.0.113.7:7777', false], ['game-1', '127.0.0.1:7001', true]], $report->get('seats'));
        self::assertSame('player-2', $report->get('checked'));
        self::assertNull($report->get('refused'));
        self::assertSame(['Portal 2', '2011', ['Valve'], 'omniplay-bare/1.0'], $report->get('json'));
        self::assertNull($report->get('json-404'));
        self::assertSame('Example answered 500 for GET /games/3. Internal error', $report->get('json-error'));

        $symbols = $report->strings('loaded.symbols');
        self::assertContains('Omniplay\Testing\FixedProvider', $symbols);
        self::assertContains('Omniplay\Seating', $symbols);
        self::assertSame([], self::foreign($symbols), 'no class of a framework, an HTTP client or an application');
        self::assertSame([], self::outside($report->strings('loaded.files'), $report->strings('loaded.eager')), 'no file but the package and what it requires');
    }
}
