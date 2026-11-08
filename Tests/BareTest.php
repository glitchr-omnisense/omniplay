<?php

namespace Omniplay\Tests;

use Omniplay\Http\Data;
use Omniplay\Registry;
use Omniplay\Testing\BareTestCase;

/**
 * omnisense/omniplay with no framework: the harness's bare script seats two
 * players (one on a server started for them), checks their tickets as a
 * server does, and makes a provider's JSON call, in a PHP process of its own -
 * nothing may load from outside the package but Symfony's HTTP client
 * contracts (interfaces only). Omnibase (Base\) is among what may not load.
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

    public function testPlayersSeatedByHandLoadNothingButOmniplayAndWhatItRequires(): void
    {
        $report = Data::of(self::php([__DIR__.'/../docker/harness/bin/bare']));

        self::assertSame(['hosting'], $report->get('capabilities.contracts'));
        self::assertSame([['eu-1', '203.0.113.7:7777', false], ['game-1', '127.0.0.1:7001', true]], $report->get('seats'));
        self::assertSame('player-2', $report->get('checked'));
        self::assertNull($report->get('refused'));
        self::assertSame(['eu-1', 3, 'omniplay-bare/1.0'], $report->get('json'));
        self::assertNull($report->get('json-404'));
        self::assertSame('Example answered 500 for GET /servers/3. Internal error', $report->get('json-error'));

        $symbols = $report->strings('loaded.symbols');
        self::assertContains('Omniplay\Seating', $symbols);
        self::assertSame([], self::foreign($symbols));
        self::assertSame([], self::outside($report->strings('loaded.files'), $report->strings('loaded.eager')));
    }
}
