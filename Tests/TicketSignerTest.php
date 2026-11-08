<?php

namespace Omniplay\Tests;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Ticket\TicketSigner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The tickets' format against Tests/vectors.json, the file every verifier in every language is checked against. */
final class TicketSignerTest extends TestCase
{
    /** @return iterable<string, array{array{name: string, payload: array<string, mixed>, secret: string, json: string, ticket: string}}> */
    public static function signed(): iterable
    {
        foreach (Vectors::sign() as $vector) {
            yield $vector['name'] => [$vector];
        }
    }

    /** @return iterable<string, array{array{name: string, ticket: string, secret: string, room: string, valid: bool, payload: ?array<string, mixed>}}> */
    public static function verified(): iterable
    {
        foreach (Vectors::verify() as $vector) {
            yield $vector['name'] => [$vector];
        }
    }

    /** @param array{name: string, payload: array<string, mixed>, secret: string, json: string, ticket: string} $vector */
    #[DataProvider('signed')]
    public function testAPayloadSignsToItsVector(array $vector): void
    {
        self::assertSame($vector['json'], json_encode($vector['payload']));
        self::assertSame($vector['ticket'], TicketSigner::sign($vector['payload'], $vector['secret']));
        self::assertSame($vector['ticket'], TicketSigner::signJson($vector['json'], $vector['secret']));
    }

    /** @param array{name: string, ticket: string, secret: string, room: string, valid: bool, payload: ?array<string, mixed>} $vector */
    #[DataProvider('verified')]
    public function testATicketHoldsOrNotAsItsVectorSays(array $vector): void
    {
        self::assertSame($vector['payload'], TicketSigner::verify($vector['ticket'], $vector['secret'], $vector['room']));
        self::assertSame($vector['valid'], null !== $vector['payload']);
    }

    public function testThereAreVectorsOfEveryKind(): void
    {
        self::assertCount(5, Vectors::sign());
        self::assertCount(22, Vectors::verify());
        self::assertCount(5, array_filter(Vectors::verify(), static fn (array $v) => $v['valid']));
    }

    public function testAnExpiryEqualToNowStillHoldsASecondLaterItDoesNot(): void
    {
        $ticket = TicketSigner::sign(['room' => 'lobby', 'exp' => 1800000000], 'secret');

        self::assertNotNull(TicketSigner::verify($ticket, 'secret', 'lobby', 1800000000));
        self::assertNull(TicketSigner::verify($ticket, 'secret', 'lobby', 1800000001));
    }

    public function testATicketWithoutItsRoomOrExpiryOrSecretIsNotSigned(): void
    {
        foreach ([[['exp' => 1], 'secret', 'room'], [['room' => '', 'exp' => 1], 'secret', 'room'], [['room' => 'lobby', 'exp' => '1'], 'secret', 'expiry'], [['room' => 'lobby', 'exp' => 1], '', 'secret'], [['room' => "lobby\xff", 'exp' => 1], 'secret', 'not JSON']] as [$payload, $secret, $said]) {
            try {
                TicketSigner::sign($payload, $secret);
                self::fail('Signed: '.json_encode($payload));
            } catch (InvalidArgumentException $e) {
                self::assertStringContainsString($said, $e->getMessage());
            }
        }
    }
}
