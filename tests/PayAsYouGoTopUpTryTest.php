<?php

declare(strict_types=1);

namespace Keboola\ManageApi\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Keboola\ManageApi\Client;
use Keboola\ManageApi\ClientException;
use Keboola\ManageApi\PayAsYouGoTopUpLockedException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class PayAsYouGoTopUpTryTest extends TestCase
{
    public function testPayAsYouGoTopUpTryPostsProjectIdAndReturnsResponseAsArray(): void
    {
        /** @var array<int, array{request: RequestInterface, response: ResponseInterface|null, error: mixed, options: array<string, mixed>}> $container */
        $container = [];
        $history = Middleware::history($container);
        // Arbitrary response body — the method makes no assumption about its shape beyond returning an array.
        $responseBody = ['invoiceId' => 'in_1ABC'];
        $mockHandler = new MockHandler([
            new Response(202, ['Content-Type' => 'application/json'], (string) json_encode($responseBody)),
        ]);

        $client = new Client([
            'url' => 'https://connection.example',
            'token' => 'super-manage-token',
            'handler' => $mockHandler,
            'middlewares' => [$history],
        ]);

        $result = $client->payAsYouGoTopUpTry(123);

        self::assertSame($responseBody, $result);

        $request = $container[0]['request'];
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/pay-as-you-go/top-up/try', $request->getUri()->getPath());
        self::assertSame('super-manage-token', $request->getHeaderLine('X-KBC-ManageApiToken'));
        self::assertSame(['idProject' => 123], json_decode((string) $request->getBody(), true));
    }

    public function testPayAsYouGoTopUpTryThrowsLockedExceptionWhenTopUpAlreadyInProgress(): void
    {
        $mockHandler = new MockHandler([
            new Response(429, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'Project "123" is already being top-upped.',
                'code' => 'topUp.alreadyInProgress',
            ])),
        ]);

        $client = new Client([
            'url' => 'https://connection.example',
            'token' => 'super-manage-token',
            'handler' => $mockHandler,
        ]);

        try {
            $client->payAsYouGoTopUpTry(123);
            self::fail('Expected PayAsYouGoTopUpLockedException was not thrown.');
        } catch (ClientException $e) {
            // The lock surfaces as the dedicated subclass while remaining a ClientException,
            // so callers that don't care about the lock keep working.
            self::assertInstanceOf(PayAsYouGoTopUpLockedException::class, $e);
            self::assertSame(429, $e->getCode());
            self::assertSame('topUp.alreadyInProgress', $e->getStringCode());
            self::assertSame('Project "123" is already being top-upped.', $e->getMessage());
        }
    }

    public function testPayAsYouGoTopUpTryThrowsGenericClientExceptionOnOtherErrors(): void
    {
        $mockHandler = new MockHandler([
            new Response(400, ['Content-Type' => 'application/json'], (string) json_encode([
                'error' => 'Automatic top-up is not configured for the project.',
                'code' => 'topUp.notConfigured',
            ])),
        ]);

        $client = new Client([
            'url' => 'https://connection.example',
            'token' => 'super-manage-token',
            'handler' => $mockHandler,
        ]);

        try {
            $client->payAsYouGoTopUpTry(123);
            self::fail('Expected ClientException was not thrown.');
        } catch (ClientException $e) {
            // A non-lock error must stay a plain ClientException, not the lock subclass.
            self::assertNotInstanceOf(PayAsYouGoTopUpLockedException::class, $e);
            self::assertSame(400, $e->getCode());
            self::assertSame('topUp.notConfigured', $e->getStringCode());
        }
    }
}
