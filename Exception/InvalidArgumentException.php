<?php

namespace Omniplay\Exception;

/** A value nothing can hold: an empty identifier, a search without words, a server of no seat, a payload no ticket can carry. */
final class InvalidArgumentException extends \InvalidArgumentException implements OmniplayException
{
}
