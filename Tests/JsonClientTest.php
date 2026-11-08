<?php

namespace Omniplay\Tests;

use Omniplay\Exception\ProviderException;
use Omniplay\Http\JsonClient;
use Omniplay\Testing\RecordedHttpClient;
use PHPUnit\Framework\TestCase;

final class JsonClientTest extends TestCase
{
    public function testAnEmptyBodyIsEmptyDataA404NullAnErrorNamesThePathOnly(): void
    {
        $http = RecordedHttpClient::routes([
            '~^POST http://docker/v1\.44/containers/a/start~' => [204, ''],
            '~^GET http://docker/v1\.44/containers/a/json~' => [200, '{"State": {"Status": "running"}}'],
            '~^POST http://docker/v1\.44/containers/create~' => [500, '{"message": "no space left on device"}'],
        ]);
        $json = new JsonClient($http, 'Docker');

        self::assertSame([], $json->request('POST', 'http://docker/v1.44/containers/a/start')?->toArray());
        self::assertSame('running', $json->get('http://docker/v1.44/containers/a/json')?->string('State.Status'));
        self::assertNull($json->get('http://docker/v1.44/containers/b/json'));
        try {
            $json->request('POST', 'http://docker/v1.44/containers/create', ['query' => ['name' => 'x', 'token' => 'not-a-real-token']]);
            self::fail('No error.');
        } catch (ProviderException $e) {
            self::assertSame('Docker answered 500 for POST /v1.44/containers/create. {"message": "no space left on device"}', $e->getMessage());
        }
    }
}
