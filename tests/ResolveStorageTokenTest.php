<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ResolveStorageTokenTest extends TestCase
{
    public function testResolveStorageTokenSendsSubjectTokenAndProjectId(): void
    {
        /** @var array<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<string, mixed>}> $container */
        $container = [];
        $history = Middleware::history($container);
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'storageToken' => 'storage-token-value',
                'projectId' => 123,
                'tokenId' => '456',
                'userId' => '789',
                'expiresAt' => '2026-01-01T00:00:00+00:00',
            ])),
        ]);

        $client = new Client([
            'url' => 'https://connection.example',
            'jwtToken' => 'service-account-jwt',
            'handler' => $mockHandler,
            'middlewares' => [$history],
        ]);

        $result = $client->resolveStorageToken(123, 'kbc_at_subject-token');

        self::assertSame([
            'storageToken' => 'storage-token-value',
            'projectId' => 123,
            'tokenId' => '456',
            'userId' => '789',
            'expiresAt' => '2026-01-01T00:00:00+00:00',
        ], $result);

        $request = $container[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame(
            '/manage/internal/auth-bridge/resolve-storage-token',
            $request->getUri()->getPath(),
        );
        self::assertSame('Bearer kbc_at_subject-token', $request->getHeaderLine('X-Subject-Token'));
        self::assertSame('Bearer service-account-jwt', $request->getHeaderLine('X-Kubernetes-Authorization'));
        self::assertSame(['projectId' => 123], json_decode((string) $request->getBody(), true));
    }

    public function testResolveStorageTokenWithSuperTokenSendsManageApiTokenHeader(): void
    {
        /** @var array<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<string, mixed>}> $container */
        $container = [];
        $history = Middleware::history($container);
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'storageToken' => 'storage-token-value',
                'projectId' => 123,
                'tokenId' => '456',
                'userId' => '789',
                'expiresAt' => null,
            ])),
        ]);

        $client = new Client([
            'url' => 'https://connection.example',
            'token' => 'super-manage-token',
            'handler' => $mockHandler,
            'middlewares' => [$history],
        ]);

        $result = $client->resolveStorageToken(123, 'kbc_pat_subject-token');

        self::assertNull($result['expiresAt']);

        $request = $container[0]['request'];
        self::assertSame('super-manage-token', $request->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('Bearer kbc_pat_subject-token', $request->getHeaderLine('X-Subject-Token'));
    }

    public function testResolveStorageTokenPropagatesClientExceptionOnError(): void
    {
        $mockHandler = new MockHandler([
            new Response(403, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'Subject token cannot access the requested project.',
                'code' => 'accessDenied',
            ])),
        ]);

        $client = new Client([
            'url' => 'https://connection.example',
            'jwtToken' => 'service-account-jwt',
            'handler' => $mockHandler,
        ]);

        $this->expectException(ClientException::class);
        $this->expectExceptionCode(403);

        $client->resolveStorageToken(123, 'kbc_at_subject-token');
    }
}
