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
namespace MuckiLogPlugin\Services;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class HttpClientLoggerDecoratorTest extends TestCase
{
    public function testRequestIsNotLoggedWhenFeatureDisabled(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('{"ok":true}', ['http_code' => 200])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())->method('info');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(false);

        $decorator = new HttpClientLoggerDecorator($mockClient, $logger, new HttpRequestLogFormatter(), $settings);
        $response = $decorator->request('GET', 'https://example.com/api');

        static::assertSame('{"ok":true}', $response->getContent());
    }

    public function testRequestAndResponseAreLoggedWhenFeatureEnabled(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('{"ok":true}', ['http_code' => 200])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::exactly(2))->method('info');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(true);

        $decorator = new HttpClientLoggerDecorator($mockClient, $logger, new HttpRequestLogFormatter(), $settings);
        $response = $decorator->request('POST', 'https://example.com/api', ['json' => ['foo' => 'bar']]);

        static::assertSame('{"ok":true}', $response->getContent());
    }

    public function testResponseIsOnlyLoggedOnce(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('{"ok":true}', ['http_code' => 200])]);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::exactly(2))->method('info');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(true);

        $decorator = new HttpClientLoggerDecorator($mockClient, $logger, new HttpRequestLogFormatter(), $settings);
        $response = $decorator->request('GET', 'https://example.com/api');

        $response->getContent();
        $response->getContent();
    }
}
