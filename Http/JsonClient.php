<?php

namespace Omniplay\Http;

use Omniplay\Exception\ProviderException;
use Omniplay\Exception\UnreachableException;
use Omnisense\Contracts\Policy\Backoff;
use Omnisense\Contracts\Policy\UsageLimitReached;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

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
 * with the wait asked (omnisense/contracts' JsonClient underneath; request()
 * is omniplay's own, for the empty body).
 */
final class JsonClient extends \Omnisense\Contracts\Http\JsonClient
{
    private readonly HttpClientInterface $client;

    /** @var array<string, string> */
    private readonly array $sent;

    private readonly ?Backoff $wait;

    /** @param array<string, string> $headers sent with every request */
    public function __construct(HttpClientInterface $http, string $provider, array $headers = [], ?Backoff $backoff = null)
    {
        parent::__construct($http, $provider, $headers, $backoff);
        $this->client = $http;
        $this->sent = $headers;
        $this->wait = $backoff;
    }

    /**
     * @param array<string, string|int|float|bool|null> $query
     * @param array<string, string>                     $headers
     *
     * @return ?Data null when the provider answers 404
     */
    public function get(string $url, array $query = [], array $headers = []): ?Data
    {
        return $this->request('GET', $url, ['query' => array_filter($query, static fn ($value) => null !== $value)], $headers);
    }

    /**
     * @param array<string, mixed>  $options json, body or query, as Symfony's HttpClient takes them
     * @param array<string, string> $headers
     *
     * @return ?Data null when the provider answers 404, an empty Data when its body is empty
     */
    public function request(string $method, string $url, array $options = [], array $headers = []): ?Data
    {
        $what = $method.' '.(parse_url($url, \PHP_URL_PATH) ?: '/');
        $options = ['headers' => $headers + $this->sent] + $options;
        $secrets = Redaction::secrets($url, $options);
        try {
            $this->wait?->clear();
            $response = $this->client->request($method, $url, $options);
            $status = $response->getStatusCode();
            $this->wait?->observe($status, $response->getHeaders(false));
            if (404 === $status) {
                return null;
            }
            $body = $response->getContent(false);
        } catch (UsageLimitReached $e) {
            throw Redaction::withoutArguments($e);
        } catch (ExceptionInterface $e) {
            throw Redaction::withoutArguments($this->unreachable(\sprintf('%s could not be reached for %s: %s', $this->provider, $what, Redaction::text(self::reason($e->getMessage()), $secrets)), Redaction::copy($e, $secrets)));
        }
        if ($status >= 400) {
            throw Redaction::withoutArguments($this->answered($what, $status, Redaction::text($body, $secrets)));
        }
        if ('' === trim($body)) {
            return new Data([]);
        }
        $data = json_decode($body, true);

        return \is_array($data) ? $this->data($data) : throw Redaction::withoutArguments($this->answered($what, $status, 'The body is not JSON.'));
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
}
