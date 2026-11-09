<?php

namespace Omniplay\Tests;

use Omniplay\Capabilities;
use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Model\Health;
use Omniplay\Model\Identifiers;
use Omniplay\Model\Image;
use Omniplay\Model\ImageKind;
use Omniplay\Model\Platform;
use Omniplay\Model\Price;
use Omniplay\Model\Query;
use Omniplay\Model\Scheme;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use PHPUnit\Framework\TestCase;

final class ModelTest extends TestCase
{
    public function testPlatformsAreFoldedIntoFamilies(): void
    {
        self::assertSame([Platform::Windows, Platform::Mac, Platform::Linux, Platform::PlayStation, Platform::Switch, Platform::Web, Platform::Ios], Platform::fromLabels(['PC (Microsoft Windows)', 'osx', 'Linux', 'PlayStation 5', 'PS4', 'Nintendo Switch 2', 'HTML5', 'iOS', 'windows', 'Amiga']));
        self::assertNull(Platform::fromLabel('Sega Saturn'));
    }

    public function testAGameGivesItsRefsPicturesAndPlatforms(): void
    {
        $game = new Game('Portal 2', Identifiers::of(['steam' => 620, 'igdb' => 72]), 'test', images: [new Image(ImageKind::Header, 'https://example.org/h.jpg'), new Image(ImageKind::Cover, 'https://example.org/c.jpg')], platforms: [Platform::Windows], price: new Price(0, 'EUR'));

        self::assertSame('steam:620', (string) $game->ref(Scheme::Steam));
        self::assertNull($game->ref(Scheme::Gog));
        self::assertSame('https://example.org/c.jpg', $game->image()?->url);
        self::assertNull($game->image(ImageKind::Logo));
        self::assertTrue($game->runsOn(Platform::Windows));
        self::assertFalse($game->runsOn(Platform::Mac));
        self::assertTrue($game->price?->isFree());
        self::assertSame(['igdb' => '72', 'steam' => '620'], $game->toArray()['identifiers']);
    }

    public function testRefsAndQueriesRefuseEmptiness(): void
    {
        self::assertSame('itch:leafo/x-moon', (string) GameRef::itch('leafo/x-moon'));
        try {
            new Query(' ');
            self::fail('No words.');
        } catch (InvalidArgumentException) {
            self::addToAssertionCount(1);
        }
        $this->expectException(InvalidArgumentException::class);
        GameRef::steam(' ');
    }

    public function testCapabilitiesSayWhatResolves(): void
    {
        $capabilities = new Capabilities(search: true, catalogue: true, lookupBy: [Scheme::Steam], player: 'a 64-bit Steam ID');

        self::assertTrue($capabilities->resolves(GameRef::steam(620)));
        self::assertFalse($capabilities->resolves(GameRef::gog(1)));
        self::assertSame(['search', 'catalogue'], $capabilities->contracts());
        self::assertSame(['steam'], $capabilities->toArray()['lookupBy']);
        self::assertFalse((new Capabilities(lookupBy: [Scheme::Steam]))->resolves(GameRef::steam(620)));
    }

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
