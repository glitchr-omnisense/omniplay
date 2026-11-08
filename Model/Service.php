<?php

namespace Omniplay\Model;

use Omniplay\Exception\InvalidArgumentException;

/**
 * A named realtime service the application seats players on: a game's
 * servers, a relay. Its secret is a reference (an environment variable's
 * name, a vault's key), read through a SecretsInterface when a ticket is
 * signed or a server started: the secret itself is never a field of
 * anything omniplay returns.
 *
 * The engine is declared, never detected: a free string the application
 * sets ("unity-mirror", "unity-netcode", "godot", "node", "custom"...),
 * stored and returned as it is. Nothing in omniplay branches on it.
 */
final readonly class Service
{
    public string $name;

    /**
     * @param ?string $url       the public address where its tickets are presented, when the service has one (a relay); a server's own address otherwise
     * @param string  $secret    the reference of the secret its servers check tickets with
     * @param int     $ttl       a ticket's life, in seconds
     * @param int     $capacity  the players a server of it takes, unless the server says otherwise
     * @param ?string $engine    what its servers run, as the application names it ("unity-mirror", "godot"...)
     */
    public function __construct(string $name, public ?string $url = null, public string $secret = 'OMNIPLAY_TICKET_SECRET', public int $ttl = 60, public int $capacity = 16, public ?string $engine = null)
    {
        $name = trim($name);
        if ('' === $name) {
            throw new InvalidArgumentException('A service is named.');
        }
        if ($ttl < 1) {
            throw new InvalidArgumentException(\sprintf('A ticket of the "%s" service lives at least a second, not %d.', $name, $ttl));
        }
        if ($capacity < 1) {
            throw new InvalidArgumentException(\sprintf('A server of the "%s" service seats at least one player, not %d.', $name, $capacity));
        }
        if ('' === trim($secret)) {
            throw new InvalidArgumentException(\sprintf('The "%s" service names its secret.', $name));
        }
        $this->name = $name;
    }

    /** @param array<string, mixed> $values as toArray() gives them, or a configuration's */
    public static function fromArray(string $name, array $values): self
    {
        $string = static fn (string $key): ?string => isset($values[$key]) && \is_scalar($values[$key]) && '' !== (string) $values[$key] ? (string) $values[$key] : null;
        $int = static fn (string $key, int $default): int => isset($values[$key]) && is_numeric($values[$key]) ? (int) $values[$key] : $default;

        return new self($name, $string('url'), $string('secret') ?? 'OMNIPLAY_TICKET_SECRET', $int('ttl', 60), $int('capacity', 16), $string('engine'));
    }

    /** @return array{name: string, url: ?string, secret: string, ttl: int, capacity: int, engine: ?string} the secret's reference, not the secret */
    public function toArray(): array
    {
        return ['name' => $this->name, 'url' => $this->url, 'secret' => $this->secret, 'ttl' => $this->ttl, 'capacity' => $this->capacity, 'engine' => $this->engine];
    }
}
