<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace MuckiLogPlugin\Services\Http;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

use MuckiLogPlugin\Services\HttpRequestLogFormatter;

class LoggingResponseTest extends TestCase
{
    public function testGetContentLogsResponseOnce(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('{"ok":true}', ['http_code' => 200])]);
        $innerResponse = $mockClient->request('GET', 'https://example.com/api');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('info');

        $response = new LoggingResponse($innerResponse, $logger, new HttpRequestLogFormatter(), 'http_client');

        static::assertSame('{"ok":true}', $response->getContent());
        static::assertSame('{"ok":true}', $response->getContent());
    }

    public function testToArrayDelegatesToWrappedResponse(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('{"foo":"bar"}', ['http_code' => 200])]);
        $innerResponse = $mockClient->request('GET', 'https://example.com/api');

        $logger = $this->createMock(LoggerInterface::class);

        $response = new LoggingResponse($innerResponse, $logger, new HttpRequestLogFormatter(), 'http_client');

        static::assertSame(['foo' => 'bar'], $response->toArray());
    }

    public function testTransportErrorIsLoggedAsError(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('', ['error' => 'Connection refused'])]);
        $innerResponse = $mockClient->request('GET', 'https://example.com/api');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');
        $logger->expects(static::never())->method('info');

        $response = new LoggingResponse($innerResponse, $logger, new HttpRequestLogFormatter(), 'http_client');

        $this->expectException(\Symfony\Component\HttpClient\Exception\TransportException::class);
        $response->getContent();
    }
}
