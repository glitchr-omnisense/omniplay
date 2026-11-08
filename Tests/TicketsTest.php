<?php

namespace Omniplay\Tests;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Exception\InvalidConfigException;
use Omniplay\Model\Service;
use Omniplay\Secret\EnvironmentSecrets;
use Omniplay\Secret\FixedSecrets;
use Omniplay\Tickets;
use PHPUnit\Framework\TestCase;

final class TicketsTest extends TestCase
{
    public function testATicketLivesTheServicesTtlAndIsReadBackWithItsClaims(): void
    {
        $now = 1800000000;
        $tickets = new Tickets(new FixedSecrets(['S' => 'not-a-real-secret']), function () use (&$now): int { return $now; });
        $service = new Service('relay', 'wss://relay.example.org', 'S', 300);

        $ticket = $tickets->mint($service, 'room-1', ['sub' => 'p-1']);

        self::assertSame(['room-1', $now + 300, ['sub' => 'p-1']], [$ticket->room, $ticket->expires->getTimestamp(), $ticket->claims]);
        self::assertSame(['sub' => 'p-1'], $tickets->verify($service, $ticket->token, 'room-1')?->claims);
        self::assertNull($tickets->verify($service, $ticket->token, 'room-2'));
        $now += 301;
        self::assertNull($tickets->verify($service, $ticket->token, 'room-1'));
    }

    public function testRoomAndExpAreNotClaims(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new Tickets(new FixedSecrets(['S' => 'x'])))->mint(new Service('game', secret: 'S'), 'r', ['exp' => 1]);
    }

    public function testASecretIsReadFromTheEnvironmentByItsName(): void
    {
        $_ENV['OMNIPLAY_TEST_SECRET'] = 'from-the-environment';
        try {
            self::assertSame('from-the-environment', (new EnvironmentSecrets())->secret('OMNIPLAY_TEST_SECRET'));
        } finally {
            unset($_ENV['OMNIPLAY_TEST_SECRET']);
        }
        $this->expectExceptionMessage('No secret in the environment variable "OMNIPLAY_TEST_SECRET".');
        $this->expectException(InvalidConfigException::class);
        (new EnvironmentSecrets())->secret('OMNIPLAY_TEST_SECRET');
    }
}
