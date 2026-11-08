<?php

namespace Omniplay\Tests;

use Omniplay\Fleet;
use Omniplay\Model\Health;
use Omniplay\Model\SeatRequest;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\Seating;
use Omniplay\Secret\FixedSecrets;
use Omniplay\Storage\InMemoryStore;
use Omniplay\Testing\FixedHosting;
use Omniplay\Ticket\TicketSigner;
use Omniplay\Tickets;
use PHPUnit\Framework\TestCase;

final class SeatingTest extends TestCase
{
    private const SECRET = 'not-a-real-secret';

    private int $now = 1800000000;

    public function testAPlayerGoesOnTheFullestReadyServerThatFitsWithATicketForIt(): void
    {
        [$fleet, $seating] = $this->seating();
        foreach ([['a', 3, ['ranked']], ['b', 6, ['ranked']], ['c', 7, []], ['d', 8, ['ranked']]] as [$id, $players, $tags]) {
            $fleet->register(new Server($id, 'game', $id.'.example.org:7777', 8, tags: $tags, engine: 'godot'));
            $fleet->heartbeat($id, $players);
        }

        $seat = $seating->seat(self::service(), new SeatRequest('player-42', tags: ['ranked'], claims: ['name' => 'Ada']));

        self::assertNotNull($seat);
        self::assertSame(['b', 7, false], [$seat->server->id, $seat->server->players, $seat->started], 'b: 6 of 8; d is full, c is not ranked');
        self::assertSame(['sub' => 'player-42', 'name' => 'Ada', 'room' => 'b', 'exp' => $this->now + 60], TicketSigner::verify($seat->ticket->token, self::SECRET, 'b', $this->now));
        self::assertSame(['address' => 'b.example.org:7777', 'ticket' => $seat->ticket->token, 'expires' => $this->now + 60, 'server' => 'b', 'service' => 'game', 'region' => null, 'engine' => 'godot', 'started' => false], $seat->toArray());
        self::assertStringNotContainsString(self::SECRET, (string) json_encode($seat->toArray()));
    }

    public function testWithNoServerAndNoHostingThereIsNoSeat(): void
    {
        [, $seating] = $this->seating();

        self::assertNull($seating->seat(self::service(), new SeatRequest('player-42')));
    }

    public function testWithNoServerOneIsStartedAndTheNextPlayerWaitsOnItRatherThanAnother(): void
    {
        $hosting = new FixedHosting(['game'], ['eu']);
        [$fleet, $seating] = $this->seating($hosting);

        $first = $seating->seat(self::service(), new SeatRequest('player-1', 'eu'));
        $second = $seating->seat(self::service(), new SeatRequest('player-2', 'eu'));

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertSame(['game-1', '127.0.0.1:7001', Health::Starting, true, 'unity-netcode'], [$first->server->id, $first->server->address, $first->server->health, $first->started, $first->server->engine]);
        self::assertSame(['game-1', 2, false], [$second->server->id, $second->server->players, $second->started]);
        self::assertSame(['game-1' => self::SECRET], $hosting->secrets, 'the server was handed the service\'s secret');
        self::assertCount(1, $fleet->servers('game'));
        self::assertNull($seating->seat(self::service(), new SeatRequest('player-3', 'us')), 'the hosting runs nothing in us');
    }

    /**
     * @return array{Fleet, Seating}
     */
    private function seating(?FixedHosting $hosting = null): array
    {
        $now = fn (): int => $this->now;
        $secrets = new FixedSecrets(['GAME_SECRET' => self::SECRET]);
        $fleet = new Fleet(new InMemoryStore(), 30, 120, $now);

        return [$fleet, new Seating($fleet, new Tickets($secrets, $now), $hosting, $secrets)];
    }

    private static function service(): Service
    {
        return new Service('game', secret: 'GAME_SECRET', ttl: 60, capacity: 8, engine: 'unity-netcode');
    }
}
