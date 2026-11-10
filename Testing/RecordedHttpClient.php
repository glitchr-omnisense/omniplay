<?php

namespace Omniplay\Testing;

/**
 * An HTTP client that answers from what it was given - a callable of the
 * method, the URL and the options, returning [status, body, headers] - and
 * keeps every request made ($requests): a provider's calls checked with no
 * network. omnisense/contracts' RecordedHttpClient, under omniplay's name
 * (routes() may give a route's headers too: a 429's Retry-After).
 */
final class RecordedHttpClient extends \Omnisense\Contracts\Testing\RecordedHttpClient
{
}
