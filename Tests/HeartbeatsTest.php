<?php

namespace Omniplay\Tests;

use Omniplay\Fleet;
use Omniplay\Heartbeats;
use Omniplay\Model\Health;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\Secret\FixedSecrets;
use Omniplay\Storage\InMemoryStore;
use Omniplay\Ticket\TicketSigner;
use PHPUnit\Framework\TestCase;

final class HeartbeatsTest extends TestCase
{
    private const SECRET = 'not-a-real-secret';

    private int $now = 1800000000;

    public function testAKnownServersHeartbeatIsHeard(): void
    {
        [$fleet, $heartbeats] = $this->heartbeats();
        $fleet->register(new Server('eu-1', 'game', '203.0.113.7:7777', 8));

        $result = $heartbeats->receive('Bearer '.Heartbeats::token('eu-1', self::SECRET, 60, $this->now), ['server' => 'eu-1', 'service' => 'game', 'players' => 5, 'health' => 'healthy']);

        self::assertSame(['status' => 200, 'reason' => 'Heard.', 'server' => ['id' => 'eu-1', 'players' => 5, 'health' => 'healthy']], $result->toArray());
        self::assertSame(['eu-1'], array_map(static fn (Server $s) => $s->id, $fleet->healthy('game')));
    }

    public function testAServerStartedByHandRegistersWithItsAddress(): void
    {
        [$fleet, $heartbeats] = $this->heartbeats();
        $token = Heartbeats::token('home-1', self::SECRET, 60, $this->now);

        self::assertSame(404, $heartbeats->receive($token, ['server' => 'home-1', 'service' => 'game', 'players' => 0])->status);
        $result = $heartbeats->receive($token, ['server' => 'home-1', 'service' => 'game', 'players' => 0, 'health' => 'starting', 'address' => '198.51.100.4:7777', 'capacity' => 4, 'region' => 'eu', 'tags' => ['casual'], 'engine' => 'godot']);

        self::assertSame(201, $result->status);
        self::assertEquals(new Server('home-1', 'game', '198.51.100.4:7777', 4, 0, Health::Starting, new \DateTimeImmutable('@'.$this->now), 'eu', ['casual'], 'godot'), $fleet->get('home-1'));
    }

    public function testAPlayersTicketAnotherSecretAnExpiredTokenOrAnotherServiceAreRefused(): void
    {
        [$fleet, $heartbeats] = $this->heartbeats();
        $fleet->register(new Server('eu-1', 'game', '203.0.113.7:7777', 8));
        $body = ['server' => 'eu-1', 'service' => 'game', 'players' => 99];
        $playerTicket = TicketSigner::sign(['sub' => 'player-42', 'room' => 'eu-1', 'exp' => $this->now + 60], self::SECRET);

        self::assertSame(401, $heartbeats->receive('Bearer '.$playerTicket, $body)->status, 'a player\'s ticket opens the server, not its heartbeat');
        self::assertSame(401, $heartbeats->receive(Heartbeats::token('eu-1', 'another-secret', 60, $this->now), $body)->status);
        self::assertSame(401, $heartbeats->receive(Heartbeats::token('eu-1', self::SECRET, 60, $this->now - 61), $body)->status);
        self::assertSame(401, $heartbeats->receive(Heartbeats::token('eu-2', self::SECRET, 60, $this->now), $body)->status, 'another server\'s token');
        self::assertSame(404, $heartbeats->receive('x', ['server' => 'eu-1', 'service' => 'relay'])->status);
        self::assertSame(400, $heartbeats->receive('x', ['players' => 1])->status);
        self::assertSame(0, $fleet->get('eu-1')?->players, 'nothing refused reached the fleet');

        $fleet->register(new Server('r-1', 'relay', 'wss://relay.example.org', 100));
        self::assertSame(409, $heartbeats->receive(Heartbeats::token('r-1', self::SECRET, 60, $this->now), ['server' => 'r-1', 'service' => 'game'])->status);
    }

    /** @return array{Fleet, Heartbeats} */
    private function heartbeats(): array
    {
        $now = fn (): int => $this->now;
        $fleet = new Fleet(new InMemoryStore(), 30, 120, $now);

        return [$fleet, new Heartbeats($fleet, new FixedSecrets(['GAME_SECRET' => self::SECRET]), [new Service('game', secret: 'GAME_SECRET', capacity: 8)], $now)];
    }
}
