<?php

namespace Omniplay\Secret;

use Omniplay\Exception\InvalidConfigException;

/** Secrets read from the environment: the reference is the variable's name ($_ENV, $_SERVER, then getenv()). */
final class EnvironmentSecrets implements SecretsInterface
{
    public function secret(string $reference): string
    {
        foreach ([$_ENV[$reference] ?? null, $_SERVER[$reference] ?? null, getenv($reference)] as $value) {
            if (\is_string($value) && '' !== $value) {
                return $value;
            }
        }

        throw new InvalidConfigException(\sprintf('No secret in the environment variable "%s".', $reference));
    }
}
