<?php

namespace Omniplay;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Model\Health;
use Omniplay\Model\Seat;
use Omniplay\Model\SeatRequest;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\Secret\SecretsInterface;

/**
 * SeatingInterface over a fleet and tickets. A player goes on the fullest
 * server that still has a seat - a ready one first, then one starting - so
 * that matches fill before new servers are used; the server's tags must
 * hold every tag asked, its region the one asked. When none fits and a
 * hosting provider is given, one is started (its secret read by the
 * service's reference), registered and seated on - when the provider's
 * capabilities say it starts that service in that region. The ticket's room is the
 * server's id, so that a ticket opens that server only; its claims are the
 * request's, the player as "sub".
 */
final class Seating implements SeatingInterface
{
    public function __construct(
        private readonly FleetInterface $fleet,
        private readonly TicketsInterface $tickets,
        private readonly ?HostingInterface $hosting = null,
        private readonly ?SecretsInterface $secrets = null,
    ) {
        if (null !== $hosting && null === $secrets) {
            throw new InvalidArgumentException('Seating that starts servers reads their secret: give it the secrets.');
        }
    }

    public function seat(Service $service, SeatRequest $request): ?Seat
    {
        $started = false;
        $server = null;
        foreach ($this->candidates($service, $request) as $candidate) {
            if (null !== $server = $this->fleet->occupy($candidate->id)) {
                break;
            }
        }
        if (null === $server && null !== $this->hosting && null !== $this->secrets && $this->starts($service, $request)) {
            $new = $this->fleet->register($this->hosting->start($service, $this->secrets->secret($service->secret), $request->region, $request->tags));
            $server = $this->fleet->occupy($new->id);
            $started = true;
        }
        if (null === $server) {
            return null;
        }

        return new Seat($request->player, $server, $this->tickets->mint($service, $server->id, ['sub' => $request->player] + $request->claims), $started);
    }

    /** Whether the hosting starts this service there: a provider says so in its capabilities; another hosting is asked. */
    private function starts(Service $service, SeatRequest $request): bool
    {
        return !$this->hosting instanceof ProviderInterface || $this->hosting->capabilities()->starts($service->name, $request->region);
    }

    /** @return list<Server> the servers that fit, in the order they are tried */
    private function candidates(Service $service, SeatRequest $request): array
    {
        $fitting = array_values(array_filter($this->fleet->available($service->name, $request->region), static fn (Server $server) => $server->has(...$request->tags)));
        usort($fitting, static fn (Server $a, Server $b) => [$b->health === Health::Healthy, $b->players, $a->id] <=> [$a->health === Health::Healthy, $a->players, $b->id]);

        return $fitting;
    }
}
