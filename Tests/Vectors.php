<?php

namespace Omniplay\Tests;

/**
 * Tests/vectors.json, read with its shapes checked: the tickets every
 * verifier in every language is held to (bin/vectors.php writes them).
 */
final class Vectors
{
    /** @return list<array{name: string, payload: array<string, mixed>, secret: string, json: string, ticket: string}> */
    public static function sign(): array
    {
        $vectors = [];
        foreach (self::list('sign') as $v) {
            $vectors[] = ['name' => self::string($v, 'name'), 'payload' => self::object($v['payload'] ?? null) ?? throw new \UnexpectedValueException('A sign vector without its payload.'), 'secret' => self::string($v, 'secret'), 'json' => self::string($v, 'json'), 'ticket' => self::string($v, 'ticket')];
        }

        return $vectors;
    }

    /** @return list<array{name: string, ticket: string, secret: string, room: string, valid: bool, payload: ?array<string, mixed>}> */
    public static function verify(): array
    {
        $vectors = [];
        foreach (self::list('verify') as $v) {
            $vectors[] = ['name' => self::string($v, 'name'), 'ticket' => self::string($v, 'ticket'), 'secret' => self::string($v, 'secret'), 'room' => self::string($v, 'room'), 'valid' => true === ($v['valid'] ?? null), 'payload' => self::object($v['payload'] ?? null)];
        }

        return $vectors;
    }

    /** @return list<array<mixed>> */
    private static function list(string $key): array
    {
        $vectors = json_decode((string) file_get_contents(__DIR__.'/vectors.json'), true);
        $list = \is_array($vectors) && \is_array($vectors[$key] ?? null) ? $vectors[$key] : [];

        return array_values(array_filter($list, 'is_array'));
    }

    /** @param array<mixed> $vector */
    private static function string(array $vector, string $key): string
    {
        return \is_string($vector[$key] ?? null) ? $vector[$key] : throw new \UnexpectedValueException(\sprintf('A vector without its "%s".', $key));
    }

    /** @return ?array<string, mixed> */
    private static function object(mixed $value): ?array
    {
        if (!\is_array($value)) {
            return null;
        }
        $object = [];
        foreach ($value as $key => $one) {
            $object[(string) $key] = $one;
        }

        return $object;
    }
}
