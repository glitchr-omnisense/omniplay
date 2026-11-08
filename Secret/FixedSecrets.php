<?php

namespace Omniplay\Secret;

use Omniplay\Exception\InvalidConfigException;

/** Secrets given by reference: a test's, or an application's that read them itself. */
final class FixedSecrets implements SecretsInterface
{
    /** @param array<string, string> $secrets by reference */
    public function __construct(private readonly array $secrets)
    {
    }

    public function secret(string $reference): string
    {
        $secret = $this->secrets[$reference] ?? '';

        return '' !== $secret ? $secret : throw new InvalidConfigException(\sprintf('No secret "%s".', $reference));
    }
}
