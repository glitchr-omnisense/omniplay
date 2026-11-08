<?php

namespace Omniplay\Tests;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Model\Health;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testAServerComesBackFromWhatAStoreKeptOfIt(): void
    {
        $server = new Server('eu-1', 'game', '203.0.113.7:7777', 16, 5, Health::Healthy, new \DateTimeImmutable('@1800000000'), 'eu', ['ranked', 'v1.4'], 'unity-mirror', 'docker', 'f00dfeed');

        $kept = json_decode((string) json_encode($server->toArray()), true);
        self::assertIsArray($kept);
        self::assertEquals($server, Server::fromArray(array_combine(array_map('strval', array_keys($kept)), $kept)));
        self::assertSame([11, true, false], [$server->free(), $server->has('ranked'), $server->has('ranked', 'casual')]);
        self::assertSame(30, $server->silence(new \DateTimeImmutable('@1800000030')));
    }

    public function testTheEngineIsStoredAndReturnedAsDeclared(): void
    {
        $service = Service::fromArray('game', ['secret' => 'GAME_SECRET', 'ttl' => '90', 'engine' => 'anything-the-application-names']);

        self::assertSame(['name' => 'game', 'url' => null, 'secret' => 'GAME_SECRET', 'ttl' => 90, 'capacity' => 16, 'engine' => 'anything-the-application-names'], $service->toArray());
    }

    public function testValuesNothingCanHoldAreRefused(): void
    {
        foreach ([
            static fn () => new Server('', 'game', 'a:1', 1),
            static fn () => new Server('a', 'game', 'a:1', 0),
            static fn () => new Server('a', 'game', 'a:1', 1, -1),
            static fn () => new Service(' '),
            static fn () => new Service('game', ttl: 0),
            static fn () => new Service('game', secret: ''),
        ] as $i => $make) {
            try {
                $make();
                self::fail('Accepted: '.$i);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }
}
