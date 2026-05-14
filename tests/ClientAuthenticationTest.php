<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Keboola\ManageApi\Auth\KubernetesServiceAccountTokenAuthenticationStrategy;
use Keboola\ManageApi\Auth\ManageApiTokenAuthenticationStrategy;
use Keboola\ManageApi\Auth\StaticJwtAuthenticationStrategy;
use Keboola\ManageApi\Client;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class ClientAuthenticationTest extends TestCase
{
    public function testUsesManageApiTokenHeaderFromLegacyTokenConfig(): void
    {
        $requests = $this->captureRequests([
            'token' => 'manage-token',
        ]);

        self::assertSame('manage-token', $requests[0]['request']->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('', $requests[0]['request']->getHeaderLine('X-Kubernetes-Authorization'));
    }

    public function testUsesManageApiTokenHeaderFromExplicitStrategy(): void
    {
        $requests = $this->captureRequests([
            'authStrategy' => new ManageApiTokenAuthenticationStrategy('manage-token'),
        ]);

        self::assertSame('manage-token', $requests[0]['request']->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('', $requests[0]['request']->getHeaderLine('X-Kubernetes-Authorization'));
    }

    public function testUsesKubernetesAuthorizationHeaderFromStaticJwtConfig(): void
    {
        $requests = $this->captureRequests([
            'jwtToken' => 'jwt-token',
        ]);

        self::assertSame('', $requests[0]['request']->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('Bearer jwt-token', $requests[0]['request']->getHeaderLine('X-Kubernetes-Authorization'));
    }

    public function testUsesKubernetesAuthorizationHeaderFromExplicitStaticJwtStrategy(): void
    {
        $requests = $this->captureRequests([
            'authStrategy' => new StaticJwtAuthenticationStrategy('jwt-token'),
        ]);

        self::assertSame('', $requests[0]['request']->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('Bearer jwt-token', $requests[0]['request']->getHeaderLine('X-Kubernetes-Authorization'));
    }

    public function testReadsKubernetesTokenFileForEveryRequest(): void
    {
        $tokenPath = tempnam(sys_get_temp_dir(), 'manage-api-jwt-');
        self::assertIsString($tokenPath);
        file_put_contents($tokenPath, "first-jwt\n");

        $container = [];
        $history = Middleware::history($container);
        $mockHandler = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
            new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
        ]);
        $client = new Client([
            'url' => 'https://connection.example',
            'kubernetesTokenPath' => $tokenPath,
            'handler' => $mockHandler,
            'middlewares' => [$history],
        ]);

        $client->verifyToken();
        file_put_contents($tokenPath, "second-jwt\n");
        $client->verifyToken();

        unlink($tokenPath);

        self::assertSame('Bearer first-jwt', $container[0]['request']->getHeaderLine('X-Kubernetes-Authorization'));
        self::assertSame('Bearer second-jwt', $container[1]['request']->getHeaderLine('X-Kubernetes-Authorization'));
    }

    public function testUsesKubernetesAuthorizationHeaderFromExplicitFileStrategy(): void
    {
        $tokenPath = tempnam(sys_get_temp_dir(), 'manage-api-jwt-');
        self::assertIsString($tokenPath);
        file_put_contents($tokenPath, 'jwt-token');

        $requests = $this->captureRequests([
            'authStrategy' => new KubernetesServiceAccountTokenAuthenticationStrategy($tokenPath),
        ]);

        unlink($tokenPath);

        self::assertSame('', $requests[0]['request']->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame('Bearer jwt-token', $requests[0]['request']->getHeaderLine('X-Kubernetes-Authorization'));
    }

    public function testRejectsMissingAuthenticationConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Exactly one authentication option must be set');

        $this->createClient([]);
    }

    public function testRejectsMultipleAuthenticationConfigurations(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Exactly one authentication option must be set');

        $this->createClient([
            'token' => 'manage-token',
            'jwtToken' => 'jwt-token',
        ]);
    }

    public function testRejectsEmptyStaticJwtToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT token must not be empty');

        new StaticJwtAuthenticationStrategy(' ');
    }

    public function testRejectsEmptyKubernetesTokenFile(): void
    {
        $tokenPath = tempnam(sys_get_temp_dir(), 'manage-api-jwt-');
        self::assertIsString($tokenPath);
        file_put_contents($tokenPath, "\n");

        $client = $this->createClient([
            'kubernetesTokenPath' => $tokenPath,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kubernetes service account token file is empty');

        try {
            $client->verifyToken();
        } finally {
            unlink($tokenPath);
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return list<array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<string, mixed>}>
     */
    private function captureRequests(array $config): array
    {
        /** @var array<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<string, mixed>}> $container */
        $container = [];
        $history = Middleware::history($container);
        $client = $this->createClient($config + [
            'handler' => new MockHandler([
                new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
            ]),
            'middlewares' => [$history],
        ]);

        $client->verifyToken();

        /** @var list<array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<string, mixed>}> $requests */
        $requests = $container;

        return $requests;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createClient(array $config): Client
    {
        return new Client($config + [
            'url' => 'https://connection.example',
            'handler' => new MockHandler([
                new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
            ]),
        ]);
    }
}
