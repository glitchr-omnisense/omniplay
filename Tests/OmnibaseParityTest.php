<?php

namespace Omniplay\Tests;

use Omniplay\Ticket\TicketSigner;
use PHPUnit\Framework\TestCase;

/**
 * Omniplay's signer and omnibase's (Base\Service\Ticket\TicketSigner, the
 * same format written first in that Symfony layer) give the same answer on
 * every vector. Omniplay does not require omnibase: the test runs when its
 * class can be loaded - installed beside, or its file named in
 * OMNIBASE_TICKET_SIGNER (the harness mounts it there) - and is skipped
 * otherwise.
 */
final class OmnibaseParityTest extends TestCase
{
    private const BASE = 'Base\Service\Ticket\TicketSigner';

    public static function setUpBeforeClass(): void
    {
        $file = getenv('OMNIBASE_TICKET_SIGNER');
        if (!class_exists(self::BASE) && \is_string($file) && is_file($file)) {
            require_once $file;
        }
    }

    protected function setUp(): void
    {
        if (!class_exists(self::BASE)) {
            self::markTestSkipped('omnibase\'s TicketSigner is not here: set OMNIBASE_TICKET_SIGNER to its file.');
        }
    }

    public function testEverySignVectorSignsTheSameInBoth(): void
    {
        $n = 0;
        foreach (Vectors::sign() as $vector) {
            $base = self::base('sign', $vector['payload'], $vector['secret']);
            self::assertSame($vector['ticket'], $base, $vector['name']);
            self::assertSame($base, TicketSigner::sign($vector['payload'], $vector['secret']), $vector['name']);
            ++$n;
        }
        self::assertSame(5, $n);
    }

    public function testEveryVerifyVectorIsAnsweredTheSameByBoth(): void
    {
        $n = 0;
        foreach (Vectors::verify() as $vector) {
            $base = self::base('verify', $vector['ticket'], $vector['secret'], $vector['room']);
            self::assertSame($vector['payload'], $base, $vector['name']);
            self::assertSame($base, TicketSigner::verify($vector['ticket'], $vector['secret'], $vector['room']), $vector['name']);
            ++$n;
        }
        self::assertSame(22, $n);
    }

    /**
     * The one difference, outside the vectors: omnibase casts `exp` to an
     * integer, so an expiry written as a string ("4102444800") holds there;
     * omniplay reads a number only, as the other languages' verifiers do.
     * No signer of either writes one.
     */
    public function testAnExpiryWrittenAsAStringHoldsInOmnibaseOnly(): void
    {
        $ticket = TicketSigner::signJson('{"room":"lobby","exp":"4102444800"}', 'secret');

        self::assertSame(['room' => 'lobby', 'exp' => '4102444800'], self::base('verify', $ticket, 'secret', 'lobby'));
        self::assertNull(TicketSigner::verify($ticket, 'secret', 'lobby'));
    }

    /** omnibase's TicketSigner::$method(), called by name: omniplay does not know the class. */
    private static function base(string $method, mixed ...$arguments): mixed
    {
        $callable = [self::BASE, $method];
        self::assertTrue(\is_callable($callable));

        return $callable(...$arguments);
    }
}
