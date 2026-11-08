<?php

namespace Omniplay\Model;

/**
 * What Heartbeats::receive() made of a server's heartbeat: the HTTP status
 * the application answers with, why, and the server as the fleet now holds
 * it (null when refused).
 */
final readonly class HeartbeatResult
{
    public function __construct(public int $status, public string $reason, public ?Server $server = null)
    {
    }

    public function accepted(): bool
    {
        return $this->status < 300;
    }

    /** @return array{status: int, reason: string, server: ?array{id: string, players: int, health: string}} the body to answer with: nothing secret */
    public function toArray(): array
    {
        return ['status' => $this->status, 'reason' => $this->reason, 'server' => null === $this->server ? null : ['id' => $this->server->id, 'players' => $this->server->players, 'health' => $this->server->health->value]];
    }
}
