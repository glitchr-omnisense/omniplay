<?php

namespace Omniplay\Secret;

/**
 * A secret by its reference: the application's environment, a vault, a
 * fixed list in a test. A Service names its secret by reference so that the
 * secret is never a field of anything stored or returned.
 */
interface SecretsInterface
{
    /** @throws \Omniplay\Exception\InvalidConfigException when there is no such secret, or it is empty */
    public function secret(string $reference): string;
}
