<?php

namespace Omniplay\Tests;

use Omniplay\Capabilities;
use Omniplay\Config;
use Omniplay\Exception\InvalidConfigException;
use Omniplay\Exception\NotSupportedException;
use Omniplay\ProviderFactory;
use Omniplay\ProviderInterface;
use Omniplay\Registry;
use Omniplay\Testing\FixedHosting;
use Omniplay\Testing\FixedHostingFactory;
use PHPUnit\Framework\TestCase;

final class RegistryTest extends TestCase
{
    public function testProvidersAreBuiltOnceFromTheirFactoryAndOptions(): void
    {
        $registry = new Registry([new FixedHostingFactory(), self::bare()], ['local' => ['factory' => 'fixed', 'options' => ['services' => ['game'], 'regions' => ['eu']]], 'other' => ['factory' => 'bare', 'options' => ['key' => 'not-a-real-key']]]);

        self::assertSame($registry->get('local'), $registry->get('local'));
        self::assertSame(['local', 'other'], $registry->names());
        self::assertSame(['fixed', 'bare'], $registry->factories());
        self::assertSame(['key' => 'not-a-real-key'], $registry->options('other'));
        self::assertFalse($registry->has('nowhere'));
        self::assertInstanceOf(FixedHosting::class, $registry->hosting('local'));
        self::assertSame(['contracts' => ['hosting'], 'services' => ['game'], 'regions' => ['eu'], 'where' => 'nowhere: 127.0.0.1, in memory'], $registry->get('local')->capabilities()->toArray());
    }

    public function testTheFirstProviderThatStartsAServiceThereIsFound(): void
    {
        $registry = new Registry([new FixedHostingFactory(), self::bare()], ['other' => ['factory' => 'bare', 'options' => ['key' => 'x']], 'eu' => ['factory' => 'fixed', 'options' => ['services' => ['game'], 'regions' => ['eu']]]]);

        self::assertSame($registry->get('eu'), $registry->hostingFor('game', 'eu'));
        self::assertSame($registry->get('eu'), $registry->hostingFor('game'));
        self::assertNull($registry->hostingFor('game', 'us'));
        self::assertNull($registry->hostingFor('relay'));
    }

    public function testACapabilityTheProviderLacksIsSaid(): void
    {
        $registry = new Registry([self::bare()], ['other' => ['factory' => 'bare', 'options' => ['key' => 'x']]]);

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('does not start servers');
        $registry->hosting('other');
    }

    public function testAMissingOptionAndAnUnknownFactoryAreSaid(): void
    {
        try {
            (new Registry([self::bare()], ['other' => ['factory' => 'bare']]))->get('other');
            self::fail('No key.');
        } catch (InvalidConfigException $e) {
            self::assertSame('The "bare" provider needs: key.', $e->getMessage());
        }
        $this->expectExceptionMessage('No "docker" factory for the "local" hosting provider; installed: fixed.');
        (new Registry([new FixedHostingFactory()], ['local' => ['factory' => 'docker']]))->get('local');
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
                        return new Capabilities();
                    }
                };
            }
        };
    }
}
