<?php

namespace Omniplay;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Model\Service;
use Omniplay\Model\Ticket;
use Omniplay\Secret\SecretsInterface;
use Omniplay\Ticket\TicketSigner;

/**
 * TicketsInterface over Ticket\TicketSigner, each service's secret read by
 * its reference. The payload is the claims, then "room", then "exp" (now
 * plus the service's ttl).
 */
final class Tickets implements TicketsInterface
{
    /** @var \Closure(): int */
    private readonly \Closure $now;

    /** @param ?\Closure(): int $now the clock, in Unix seconds (time() when null) */
    public function __construct(private readonly SecretsInterface $secrets, ?\Closure $now = null)
    {
        $this->now = $now ?? time(...);
    }

    public function mint(Service $service, string $room, array $claims = []): Ticket
    {
        if (\array_key_exists('room', $claims) || \array_key_exists('exp', $claims)) {
            throw new InvalidArgumentException('"room" and "exp" are the ticket\'s own: they are not claims.');
        }
        $exp = $this->now() + $service->ttl;
        $token = TicketSigner::sign($claims + ['room' => $room, 'exp' => $exp], $this->secrets->secret($service->secret));

        return new Ticket($token, $room, new \DateTimeImmutable('@'.$exp), $claims);
    }

    public function verify(Service $service, string $token, string $room): ?Ticket
    {
        $payload = TicketSigner::verify($token, $this->secrets->secret($service->secret), $room, $this->now());
        if (null === $payload) {
            return null;
        }
        $exp = $payload['exp'];
        unset($payload['room'], $payload['exp']);

        return new Ticket($token, $room, new \DateTimeImmutable('@'.(\is_int($exp) || \is_float($exp) ? (int) $exp : 0)), $payload);
    }

    private function now(): int
    {
        return ($this->now)();
    }
}
