<?php

namespace Omniplay\Exception;

/**
 * A platform refused a player's proof, or the proof is not one: the message
 * says why, as the platform said it ("Steam refused the ticket: Invalid
 * ticket (error 101)."). Never a network failure, never a provider's error.
 */
final class IdentityRefusedException extends \RuntimeException implements OmniplayException
{
    public static function refused(string $platform, string $proof, string $reason): self
    {
        return new self(\sprintf('%s refused the %s: %s', $platform, $proof, rtrim(mb_substr(trim($reason), 0, 300), '.').'.'));
    }
}
