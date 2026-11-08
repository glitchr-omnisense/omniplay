<?php

namespace Omniplay\Exception;

/** The provider could not be reached: a network error, a timeout, a socket that is not there. */
final class UnreachableException extends \RuntimeException implements OmniplayException
{
}
