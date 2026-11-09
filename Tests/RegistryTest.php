<?php

namespace Omniplay\Tests;

use Omniplay\Capabilities;
use Omniplay\Config;
use Omniplay\Exception\InvalidConfigException;
use Omniplay\Exception\NotSupportedException;
use Omniplay\Model\Game;
use Omniplay\Model\GameRef;
use Omniplay\Model\Identifiers;
use Omniplay\Model\Scheme;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;
use Omniplay\Registry;
use Omniplay\Testing\FixedHosting;
use Omniplay\Testing\FixedHostingFactory;
use Omniplay\Testing\FixedIdentityFactory;
use Omniplay\Testing\FixedProviderFactory;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testProvidersAreBuiltOnceFromTheirFactoryAndOptions(): void
    {
        $registry = new Registry([new FixedProviderFactory(), self::bare()], ['store' => ['factory' => 'fixed'], 'shop' => ['factory' => 'bare', 'options' => ['key' => 'not-a-real-key']]]);

        self::assertSame($registry->get('store'), $registry->get('store'));
        self::assertSame(['store', 'shop'], $registry->names());
        self::assertSame(['fixed', 'bare'], $registry->factories());
        self::assertSame(['key' => 'not-a-real-key'], $registry->options('shop'));
        self::assertFalse($registry->has('other'));
        self::assertSame([], $registry->library('store')->library('nobody'));
    }

    public function testACapabilityTheProviderLacksIsSaid(): void
    {
        $registry = new Registry([self::bare()], ['shop' => ['factory' => 'bare', 'options' => ['key' => 'x']]]);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('does not list a player\'s games');
        $registry->library('shop');
    }

    public function testAMissingOptionAndAnUnknownFactoryAreNamed(): void
    {
        try {
            (new Registry([self::bare()], ['shop' => ['factory' => 'bare']]))->get('shop');
            self::fail('No key.');
        } catch (InvalidConfigException $e) {
            self::assertSame('The "bare" provider needs: key.', $e->getMessage());
        }
        try {
            (new Registry([new FixedProviderFactory()], ['store' => ['factory' => 'steam']]))->get('store');
            self::fail('No factory.');
        } catch (InvalidConfigException $e) {
            self::assertSame('No "steam" factory for the "store" game provider; installed: fixed.', $e->getMessage());
        }
        $this->expectExceptionMessage('No "docker" factory for the "local" game provider; installed: fixed-hosting.');
        (new Registry([new FixedHostingFactory()], ['local' => ['factory' => 'docker']]))->get('local');
    }

    public function testAGameIsAskedOfTheFirstProviderThatResolvesItsIdentifier(): void
    {
        $portal = new Game('Portal 2', Identifiers::of(['steam' => 620]), 'fixed');
        $registry = new Registry([self::bare(), new FixedProviderFactory([$portal])], ['shop' => ['factory' => 'bare', 'options' => ['key' => 'x']], 'store' => ['factory' => 'fixed']]);

        self::assertSame($portal, $registry->game(GameRef::steam(620)));
        self::assertNull($registry->game(GameRef::steam(400)));
        self::assertNull($registry->game(GameRef::steam(620), null, null, 'shop'));
    }

    public function testHostingProvidersAreBuiltOnceFromTheirFactoryAndOptions(): void
    {
        $registry = new Registry([new FixedHostingFactory(), self::bare()], ['local' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game'], 'regions' => ['eu']]], 'other' => ['factory' => 'bare', 'options' => ['key' => 'not-a-real-key']]]);

        self::assertSame($registry->get('local'), $registry->get('local'));
        self::assertSame(['fixed-hosting', 'bare'], $registry->factories());
        self::assertInstanceOf(FixedHosting::class, $registry->hosting('local'));
        self::assertSame(['contracts' => ['hosting'], 'lookupBy' => [], 'prices' => false, 'player' => null, 'attribution' => null, 'terms' => [], 'keyless' => false, 'services' => ['game'], 'regions' => ['eu'], 'where' => 'nowhere: 127.0.0.1, in memory'], $registry->get('local')->capabilities()->toArray());
    }

    public function testTheFirstProviderThatStartsAServiceThereIsFound(): void
    {
        $registry = new Registry([new FixedHostingFactory(), self::bare()], ['other' => ['factory' => 'bare', 'options' => ['key' => 'x']], 'eu' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game'], 'regions' => ['eu']]]]);

        self::assertSame($registry->get('eu'), $registry->hostingFor('game', 'eu'));
        self::assertSame($registry->get('eu'), $registry->hostingFor('game'));
        self::assertNull($registry->hostingFor('game', 'us'));
        self::assertNull($registry->hostingFor('relay'));
    }

    public function testAHostingCapabilityTheProviderLacksIsSaid(): void
    {
        $registry = new Registry([self::bare()], ['other' => ['factory' => 'bare', 'options' => ['key' => 'x']]]);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('does not start servers');
        $registry->hosting('other');
    }

    public function testOneRegistryHoldsAStoreAHostAndAPlatformsPlayers(): void
    {
        $portal = new Game('Portal 2', Identifiers::of(['steam' => 620]), 'fixed');
        $registry = new Registry([new FixedProviderFactory([$portal]), new FixedHostingFactory(), new FixedIdentityFactory()], [
            'store' => ['factory' => 'fixed'],
            'local' => ['factory' => 'fixed-hosting', 'options' => ['services' => ['game']]],
            'steam' => ['factory' => 'fixed-identity', 'options' => ['platform' => 'steam', 'players' => ['ticket-1' => '76561197960287930']]],
        ]);

        self::assertSame($portal, $registry->game(GameRef::steam(620)));
        self::assertSame($registry->get('local'), $registry->hostingFor('game'));
        self::assertSame('steam:76561197960287930', $registry->identityFor('steam')?->verify('ticket-1')->player());
        self::assertSame(['search', 'catalogue', 'library', 'ownership'], $registry->get('store')->capabilities()->contracts());
        self::assertSame(['hosting'], $registry->get('local')->capabilities()->contracts());
        self::assertSame(['identity'], $registry->get('steam')->capabilities()->contracts());
    }

    private static function bare(): ProviderFactory
    {
        return new class extends ProviderFactory {
            protected function populate(Config $c): void
            {
                $c->defaults(['omniplay.factory_name' => 'bare', 'omniplay.required_options' => ['key']]);
            }

            protected function build(Config $c): ProviderInterface
            {
                return new class implements ProviderInterface {
                    public function getName(): string
                    {
                        return 'bare';
                    }

                    public function getTitle(): string
                    {
                        return 'Bare';
                    }

                    public function capabilities(): Capabilities
                    {
                        return new Capabilities(lookupBy: [Scheme::Steam]);
                    }
                };
            }
        };
    }
}
