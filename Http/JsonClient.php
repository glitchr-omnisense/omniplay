<?php

namespace Omniplay\Http;

use Omniplay\Exception\ProviderException;
use Omniplay\Exception\UnreachableException;

/**
 * A provider's JSON calls over the application's HTTP client: the
 * provider's headers on every request, a 404 read as "not known" (null),
 * any other error status as a ProviderException naming the path only (never
 * the query, where a key may sit), a network failure as an
 * UnreachableException naming the path only too, with the HTTP client's
 * reason without the address it quotes (reason()). Its previous exception is
 * a copy of the client's (Redaction::copy(): same class and code, the key
 * masked in its message), and neither keeps its trace's arguments, where the
 * request's query and headers stood. An empty body (Docker's 204 to a
 * container started, 304 to one already stopped) reads as an empty Data.
 *
 * Given the provider's Backoff (Config::backoff() in a factory's build()),
 * it waits while the provider asked to and reads every response's
 * Retry-After and RateLimit fields: a 429 is a UsageLimitReachedException
 * with the wait asked. All of it is omnisense/contracts' JsonClient (since
 * 5bac28b, the empty body too); omniplay gives its Data and its exceptions.
 */
final class JsonClient extends \Omnisense\Contracts\Http\JsonClient
{
    /**
     * @param array<string, string|int|float|bool|null> $query
     * @param array<string, string>                     $headers
     *
     * @return ?Data null when the provider answers 404
     */
    public function get(string $url, array $query = [], array $headers = []): ?Data
    {
        return self::own(parent::get($url, $query, $headers));
    }

    /**
     * @param array<string, mixed>  $options json, body or query, as Symfony's HttpClient takes them
     * @param array<string, string> $headers
     *
     * @return ?Data null when the provider answers 404, an empty Data when its body is empty
     */
    public function request(string $method, string $url, array $options = [], array $headers = []): ?Data
    {
        return self::own(parent::request($method, $url, $options, $headers));
    }

    /** @param array<mixed> $data */
    protected function data(array $data): Data
    {
        return new Data($data);
    }

    protected function answered(string $what, int $status, string $detail): \Throwable
    {
        return ProviderException::answered($this->provider, $what, $status, $detail);
    }

    protected function unreachable(string $message, \Throwable $previous): \Throwable
    {
        return new UnreachableException($message, 0, $previous);
    }

    /** omniplay's Data, as data() makes it. */
    private static function own(?\Omnisense\Contracts\Http\Data $data): ?Data
    {
        return null === $data || $data instanceof Data ? $data : new Data($data->toArray());
    }
}
