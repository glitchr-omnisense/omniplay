<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/**
 * A game server of a service, as the fleet knows it: where players reach
 * it, how many it seats and holds, what it last said of itself and when.
 * Its id is the room its tickets name. A server started by a hosting
 * provider carries that provider's name and its handle there (a container's
 * id), so that it can be stopped.
 *
 * The engine is declared, as the Service's: stored and returned, never
 * read by omniplay.
 */
final readonly class Server
{
    public string $id;

    /**
     * @param string       $address   where players reach it, as their client takes it ("203.0.113.7:7777", "wss://eu1.example.org/play")
     * @param list<string> $tags      what a seat may ask of it ("ranked", "map:harbour", "v1.4")
     * @param ?string      $provider  the hosting provider that started it, by its configured name
     * @param ?string      $instance  its handle there (a container's id)
     */
    public function __construct(
        string $id,
        public string $service,
        public string $address,
        public int $capacity,
        public int $players = 0,
        public Health $health = Health::Starting,
        public ?\DateTimeImmutable $heartbeat = null,
        public ?string $region = null,
        public array $tags = [],
        public ?string $engine = null,
        public ?string $provider = null,
        public ?string $instance = null,
    ) {
        $id = trim($id);
        if ('' === $id) {
            throw new InvalidArgumentException('A server is named by a non-empty identifier.');
        }
        if ('' === trim($service) || '' === trim($address)) {
            throw new InvalidArgumentException(\sprintf('The server "%s" names its service and its address.', $id));
        }
        if ($capacity < 1 || $players < 0) {
            throw new InvalidArgumentException(\sprintf('The server "%s" seats at least one player and holds none or more, not %d and %d.', $id, $capacity, $players));
        }
        $this->id = $id;
    }

    /** The seats left. */
    public function free(): int
    {
        return max(0, $this->capacity - $this->players);
    }

    /** Whether it carries every tag asked. */
    public function has(string ...$tags): bool
    {
        return [] === array_diff($tags, $this->tags);
    }

    /** Seconds since its last heartbeat (since ever: PHP_INT_MAX, when it never sent one). */
    public function silence(\DateTimeImmutable $now): int
    {
        return null === $this->heartbeat ? \PHP_INT_MAX : $now->getTimestamp() - $this->heartbeat->getTimestamp();
    }

    public function withHeartbeat(int $players, Health $health, \DateTimeImmutable $at): self
    {
        return new self($this->id, $this->service, $this->address, $this->capacity, $players, $health, $at, $this->region, $this->tags, $this->engine, $this->provider, $this->instance);
    }

    public function withPlayers(int $players): self
    {
        return new self($this->id, $this->service, $this->address, $this->capacity, $players, $this->health, $this->heartbeat, $this->region, $this->tags, $this->engine, $this->provider, $this->instance);
    }

    /** @return array{id: string, service: string, address: string, capacity: int, players: int, health: string, heartbeat: ?string, region: ?string, tags: list<string>, engine: ?string, provider: ?string, instance: ?string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'service' => $this->service,
            'address' => $this->address,
            'capacity' => $this->capacity,
            'players' => $this->players,
            'health' => $this->health->value,
            'heartbeat' => $this->heartbeat?->format(\DATE_ATOM),
            'region' => $this->region,
            'tags' => $this->tags,
            'engine' => $this->engine,
            'provider' => $this->provider,
            'instance' => $this->instance,
        ];
    }

    /**
     * A server as toArray() gave it: what an application's store reads back.
     *
     * @param array<string, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        $string = static fn (string $key): ?string => isset($values[$key]) && \is_scalar($values[$key]) && '' !== (string) $values[$key] ? (string) $values[$key] : null;
        $heartbeat = $string('heartbeat');
        $tags = \is_array($values['tags'] ?? null) ? array_values(array_map('strval', array_filter($values['tags'], 'is_scalar'))) : [];

        return new self(
            (string) $string('id'),
            (string) $string('service'),
            (string) $string('address'),
            is_numeric($values['capacity'] ?? null) ? (int) $values['capacity'] : 0,
            is_numeric($values['players'] ?? null) ? (int) $values['players'] : 0,
            Health::tryFrom((string) $string('health')) ?? Health::Down,
            null !== $heartbeat ? new \DateTimeImmutable($heartbeat) : null,
            $string('region'),
            $tags,
            $string('engine'),
            $string('provider'),
            $string('instance'),
        );
    }
}
