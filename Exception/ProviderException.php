<?php

namespace Omniplay\Exception;

/**
 * The provider answered, and not with what was asked: an error status, a body
 * that is not JSON. answered() gives it the HTTP status as its code (410: a
 * server gone already, which a provider may take as stopped), status() reads
 * it; 0 for a body that could not be read (omnisense/contracts'
 * ProviderException, since 5bac28b).
 */
final class ProviderException extends \Omnisense\Contracts\Exception\ProviderException implements OmniplayException
{
}
