<?php

namespace Omniplay\Exception;

/** A provider asked for with options it cannot work with, or not configured at all; a secret not found. */
final class InvalidConfigException extends \InvalidArgumentException implements OmniplayException
{
}
