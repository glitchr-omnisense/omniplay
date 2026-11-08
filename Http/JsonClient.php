<?php

namespace Omniplay\Http;

use Omniplay\Exception\ProviderException;
use Omniplay\Exception\UnreachableException;
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
 */
final class JsonClient
{
    /** @param array<string, string> $headers sent with every request */
    public function __construct(private readonly HttpClientInterface $http, private readonly string $provider, private readonly array $headers = [])
    {
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
     * @return ?Data null when the provider answers 404
     */
    public function request(string $method, string $url, array $options = [], array $headers = []): ?Data
    {
        $what = $method.' '.(parse_url($url, \PHP_URL_PATH) ?: '/');
        $options = ['headers' => $headers + $this->headers] + $options;
        try {
            $response = $this->http->request($method, $url, $options);
            $status = $response->getStatusCode();
            if (404 === $status) {
                return null;
            }
            $body = $response->getContent(false);
        } catch (ExceptionInterface $e) {
            throw Redaction::withoutArguments(new UnreachableException(\sprintf('%s could not be reached for %s: %s', $this->provider, $what, self::reason($e->getMessage())), 0, Redaction::copy($e, Redaction::secrets($url, $options))));
        }
        if ($status >= 400) {
            throw Redaction::withoutArguments(ProviderException::answered($this->provider, $what, $status, Redaction::text($body, Redaction::secrets($url, $options))));
        }
        if ('' === trim($body)) {
            return new Data([]);
        }
        $data = json_decode($body, true);

        return \is_array($data) ? new Data($data) : throw Redaction::withoutArguments(ProviderException::answered($this->provider, $what, $status, 'The body is not JSON.'));
    }

    /**
     * An HTTP client's message without the addresses it quotes: Symfony's
     * ' for "<url>"' dropped, any other address cut to its scheme, host and
     * path (no credentials, no query, no fragment).
     */
    public static function reason(string $message): string
    {
        $message = (string) preg_replace('~\s+for\s+"[a-z][a-z0-9+.\-]*://[^"]*"~i', '', $message);
        $message = (string) preg_replace_callback('~[a-z][a-z0-9+.\-]*://[^\s"\'<>]+~i', static function (array $match): string {
            $parts = parse_url($match[0]);
            if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
                return '(an address)';
            }

            return $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
        }, $message);
        $message = rtrim(trim($message), '.');

        return '' !== $message ? $message.'.' : 'no reason given.';
    }
}
