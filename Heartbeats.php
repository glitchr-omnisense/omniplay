<?php

namespace Omniplay;

use Omniplay\Exception\InvalidArgumentException;
use Omniplay\Model\Health;
use Omniplay\Model\HeartbeatResult;
use Omniplay\Model\Server;
use Omniplay\Model\Service;
use Omniplay\Secret\SecretsInterface;
use Omniplay\Ticket\TicketSigner;

/**
 * The application's end of a server's heartbeat, framework-free: the
 * application's route hands it the Authorization header and the decoded
 * JSON body, and answers with the result's status and toArray().
 *
 * A heartbeat is signed as a ticket is, with its service's secret, for the
 * room "heartbeat:<server id>" (room()): a player's ticket, whose room is the
 * server's id, never passes for one. The body names the server, its service,
 * its players and its health; a server the fleet does not know is
 * registered when the body also gives its address (a server started by
 * hand), and is a 404 otherwise. docs/heartbeat.md is the protocol in full;
 * the providers that run in the game server (omniplay/dotnet, /mirror,
 * /netcode, /godot, /node) send it.
 */
final class Heartbeats
{
    public const PREFIX = 'heartbeat:';

    /** @var array<string, Service> */
    private array $services = [];

    /** @var \Closure(): int */
    private readonly \Closure $now;

    /**
     * @param iterable<Service> $services the services whose servers may beat
     * @param ?\Closure(): int  $now      the clock, in Unix seconds (time() when null)
     */
    public function __construct(private readonly FleetInterface $fleet, private readonly SecretsInterface $secrets, iterable $services, ?\Closure $now = null)
    {
        foreach ($services as $service) {
            $this->services[$service->name] = $service;
        }
        $this->now = $now ?? time(...);
    }

    /** The room a server's heartbeat token names. */
    public static function room(string $server): string
    {
        return self::PREFIX.$server;
    }

    /** A heartbeat token for this server: what a server written in PHP sends; the providers in the game server make the same. */
    public static function token(string $server, string $secret, int $ttl = 60, ?int $now = null): string
    {
        return TicketSigner::sign(['room' => self::room($server), 'exp' => ($now ?? time()) + $ttl], $secret);
    }

    /**
     * @param string       $authorization the request's Authorization header ("Bearer <token>"), or the token alone
     * @param array<mixed> $body          the request's JSON body, decoded
     */
    public function receive(string $authorization, array $body): HeartbeatResult
    {
        $string = static fn (string $key): ?string => isset($body[$key]) && \is_scalar($body[$key]) && '' !== trim((string) $body[$key]) ? trim((string) $body[$key]) : null;
        $id = $string('server');
        $name = $string('service');
        if (null === $id || null === $name) {
            return new HeartbeatResult(400, 'A heartbeat names its "server" and its "service".');
        }
        $service = $this->services[$name] ?? null;
        if (null === $service) {
            return new HeartbeatResult(404, \sprintf('No "%s" service here.', $name));
        }
        $token = trim((string) preg_replace('~^Bearer\s+~i', '', trim($authorization)));
        if (null === TicketSigner::verify($token, $this->secrets->secret($service->secret), self::room($id), ($this->now)())) {
            return new HeartbeatResult(401, 'The heartbeat\'s token does not hold for this server.');
        }
        $known = $this->fleet->get($id);
        if (null !== $known && $known->service !== $service->name) {
            return new HeartbeatResult(409, \sprintf('The server "%s" is of the "%s" service, not "%s".', $id, $known->service, $service->name));
        }
        $players = is_numeric($body['players'] ?? null) ? max(0, (int) $body['players']) : 0;
        $health = Health::tryFrom((string) $string('health')) ?? Health::Healthy;
        if (null !== $known) {
            $server = $this->fleet->heartbeat($id, $players, $health);

            return null !== $server ? new HeartbeatResult(200, 'Heard.', $server) : new HeartbeatResult(404, \sprintf('The server "%s" is gone from the fleet.', $id));
        }
        $address = $string('address');
        if (null === $address) {
            return new HeartbeatResult(404, \sprintf('No server "%s" in the fleet: a heartbeat that gives its "address" registers it.', $id));
        }
        try {
            $tags = \is_array($body['tags'] ?? null) ? array_values(array_map('strval', array_filter($body['tags'], 'is_scalar'))) : [];
            $capacity = is_numeric($body['capacity'] ?? null) ? (int) $body['capacity'] : $service->capacity;
            $server = $this->fleet->register(new Server($id, $service->name, $address, $capacity, $players, $health, null, $string('region'), $tags, $string('engine') ?? $service->engine));
        } catch (InvalidArgumentException $e) {
            return new HeartbeatResult(400, $e->getMessage());
        }

        return new HeartbeatResult(201, 'Registered.', $server);
    }
}
