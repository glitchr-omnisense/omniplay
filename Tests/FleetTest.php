<?php

namespace Omniplay\Tests;

use Omniplay\Fleet;
use Omniplay\Model\Health;
use Omniplay\Model\Server;
use Omniplay\Storage\InMemoryStore;
use PHPUnit\Framework\TestCase;

final class FleetTest extends TestCase
{
    private int $now = 1800000000;

    public function testAServerIsHealthyWhileItsHeartbeatsComeWithinTheTtl(): void
    {
        $fleet = $this->fleet();
        $fleet->register(self::server('a'));

        self::assertSame([], $fleet->healthy('game'), 'starting: not healthy yet');
        self::assertSame(3, $fleet->heartbeat('a', 3)?->players);
        self::assertSame(['a'], self::ids($fleet->healthy('game')));
        $this->now += 30;
        self::assertSame(['a'], self::ids($fleet->healthy('game')), 'heard 30 s ago, the ttl: still healthy');
        ++$this->now;
        self::assertSame([], $fleet->healthy('game'));
        self::assertNull($fleet->heartbeat('nobody', 1));
    }

    public function testStaleServersAreRetiredAStartingOneAfterItsLongerWait(): void
    {
        $fleet = $this->fleet();
        $fleet->register(self::server('ready'));
        $fleet->heartbeat('ready', 0);
        $fleet->register(self::server('booting'));
        $fleet->register(self::server('leaving'));
        $fleet->heartbeat('leaving', 0, Health::Down);

        self::assertSame(['leaving'], self::ids($fleet->retireStale()), 'down: retired at once');
        $this->now += 31;
        self::assertSame(['ready'], self::ids($fleet->retireStale()));
        $this->now += 90;
        self::assertSame(['booting'], self::ids($fleet->retireStale()), '121 s without a heartbeat while starting');
        self::assertSame([], $fleet->servers('game'));
    }

    public function testASeatIsCountedAtOnceAndAFullOrDrainingServerTakesNone(): void
    {
        $fleet = $this->fleet();
        $fleet->register(self::server('a', capacity: 2));
        $fleet->heartbeat('a', 1);

        self::assertSame(2, $fleet->occupy('a')?->players);
        self::assertNull($fleet->occupy('a'), 'full');
        self::assertSame([], $fleet->available('game'));
        $fleet->heartbeat('a', 0, Health::Draining);
        self::assertNull($fleet->occupy('a'), 'draining');
    }

    public function testServersAreListedByServiceAndRegion(): void
    {
        $fleet = $this->fleet();
        $fleet->register(self::server('eu', region: 'eu'));
        $fleet->register(self::server('us', region: 'us'));
        $fleet->register(new Server('relay-1', 'relay', 'wss://relay.example.org', 100));

        self::assertSame(['eu', 'us'], self::ids($fleet->servers('game')));
        self::assertSame(['us'], self::ids($fleet->servers('game', 'us')));
        self::assertSame(['eu'], self::ids($fleet->available('game', 'eu')), 'starting, within its wait');
        $fleet->remove('eu');
        self::assertNull($fleet->get('eu'));
    }

    private function fleet(): Fleet
    {
        return new Fleet(new InMemoryStore(), 30, 120, fn (): int => $this->now);
    }

    private static function server(string $id, int $capacity = 8, ?string $region = null): Server
    {
        return new Server($id, 'game', '127.0.0.1:7777', $capacity, region: $region);
    }

    /**
     * @param list<Server> $servers
     *
     * @return list<string>
     */
    private static function ids(array $servers): array
    {
        return array_map(static fn (Server $server) => $server->id, $servers);
    }
}
