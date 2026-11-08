<?php

namespace Omniplay\Tests;

use Omniplay\Exception\IdentityRefusedException;
use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Exception\NotSupportedException;
use Omniplay\Exception\ProviderException;
use Omniplay\Fleet;
use Omniplay\Model\Identity;
use Omniplay\Model\SeatRequest;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\Registry;
use Omniplay\Seating;
use Omniplay\Secret\FixedSecrets;
use Omniplay\Storage\InMemoryStore;
use Omniplay\Testing\FixedHostingFactory;
use Omniplay\Testing\FixedIdentity;
use Omniplay\Testing\FixedIdentityFactory;
use Omniplay\Ticket\TicketSigner;
use Omniplay\Tickets;
use PHPUnit\Framework\TestCase;

/** The player identity capability: optional, found in the registry like hosting, taken by a seat as the player's id. */
final class IdentityTest extends TestCase
{
    public function testAVerifiedPlayerIsNamedByItsPlatformAndId(): void
    {
        $identity = new Identity(' Steam ', ' 76561197960287930 ', ['owner' => '76561197960287930']);

        self::assertSame('steam:76561197960287930', $identity->player());
        self::assertSame('steam:76561197960287930', (string) $identity);
        self::assertSame(['player' => 'steam:76561197960287930', 'platform' => 'steam', 'id' => '76561197960287930', 'claims' => ['owner' => '76561197960287930']], $identity->toArray());

        foreach ([['', '1'], ['steam:x', '1'], ['steam', ' ']] as [$platform, $id]) {
            try {
                new Identity($platform, $id);
                self::fail($platform.' '.$id);
            } catch (InvalidArgumentException) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testTheRegistryFindsTheProviderThatVerifiesAPlatformsPlayers(): void
    {
        $registry = new Registry([new FixedHostingFactory(), new FixedIdentityFactory()], [
            'local' => ['factory' => 'fixed'],
            'steam' => ['factory' => 'fixed-identity', 'options' => ['platform' => 'steam', 'players' => ['ticket-1' => '76561197960287930']]],
        ]);

        self::assertSame($registry->get('steam'), $registry->identity('steam'));
        self::assertSame($registry->get('steam'), $registry->identityFor('Steam'));
        self::assertNull($registry->identityFor('epic'));
        self::assertNull($registry->identityFor('steam', 'local'));
        self::assertNull($registry->hostingFor('game', null, 'steam'), 'a platform that verifies players starts no server');
        self::assertSame(['contracts' => ['identity'], 'services' => [], 'regions' => [], 'where' => 'nowhere: 1 players, in memory'], $registry->get('steam')->capabilities()->toArray());
        self::assertSame('steam:76561197960287930', $registry->identity('steam')->verify('ticket-1')->player());

        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('does not verify players');
        $registry->identity('local');
    }

    public function testAProofThePlatformDoesNotVouchForIsRefusedAloud(): void
    {
        $this->expectException(IdentityRefusedException::class);
        $this->expectExceptionMessage('Fixed identity refused the proof: not one it was given.');
        (new FixedIdentity(['ticket-1' => '1']))->verify('forged');
    }

    public function testASeatTakesAVerifiedIdentityAsItsPlayerAndNothingRequiresOne(): void
    {
        $secrets = new FixedSecrets(['GAME_SECRET' => 'not-a-real-secret']);
        $fleet = new Fleet(new InMemoryStore());
        $fleet->register(new Server('eu-1', 'game', '203.0.113.7:7777', 8));
        $seating = new Seating($fleet, new Tickets($secrets), null, null);
        $game = new Service('game', secret: 'GAME_SECRET');
        $identity = (new FixedIdentity(['ticket-1' => '76561197960287930'], 'steam'))->verify('ticket-1');

        $verified = $seating->seat($game, new SeatRequest($identity, null, [], ['name' => 'Ada']));
        $named = $seating->seat($game, new SeatRequest('player-42'));

        self::assertNotNull($verified);
        self::assertNotNull($named);
        self::assertSame('steam:76561197960287930', $verified->player);
        self::assertSame(['sub' => 'steam:76561197960287930', 'name' => 'Ada', 'room' => 'eu-1'], array_diff_key((array) TicketSigner::verify($verified->ticket->token, 'not-a-real-secret', 'eu-1', time()), ['exp' => 0]));
        self::assertSame('player-42', $named->player);
        self::assertSame($identity, (new SeatRequest($identity))->identity);
        self::assertNull((new SeatRequest('player-42'))->identity);
    }

    public function testAnErrorStatusIsTheProviderExceptionsCode(): void
    {
        self::assertSame(410, ProviderException::answered('Edgegap', 'DELETE /v1/stop/x', 410)->getCode());
        self::assertSame(0, ProviderException::answered('Example', 'GET /', 'timeout')->getCode());
    }
}
