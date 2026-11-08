<?php

namespace Omniplay\Exception;

/**
 * The provider answered, and not with what was asked: an error status, a body
 * that is not JSON. answered() gives it the HTTP status as its code (410: a
 * server gone already, which a provider may take as stopped).
 */
final class ProviderException extends \RuntimeException implements OmniplayException
{
    public static function answered(string $provider, string $what, int|string $status, string $detail = ''): self
    {
        return new self(rtrim(\sprintf('%s answered %s for %s. %s', $provider, (string) $status, $what, mb_substr(trim(strip_tags($detail)), 0, 300))), \is_int($status) ? $status : 0);
    }
}
