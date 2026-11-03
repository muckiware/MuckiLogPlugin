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

use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class StoreClientLoggingMiddlewareTest extends TestCase
{
    public function testHandlerIsCalledUnchangedWhenFeatureDisabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())->method('info');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(false);

        $middleware = new StoreClientLoggingMiddleware($logger, new HttpRequestLogFormatter(), $settings);
        $response = new Response(200, [], '{"ok":true}');
        $handler = static fn (Request $request, array $options) => Create::promiseFor($response);

        $wrapped = $middleware($handler);
        $result = $wrapped(new Request('GET', 'https://api.shopware.com/plugins'), [])->wait();

        static::assertSame($response, $result);
    }

    public function testRequestAndResponseAreLoggedWhenFeatureEnabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::exactly(2))->method('info');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(true);

        $middleware = new StoreClientLoggingMiddleware($logger, new HttpRequestLogFormatter(), $settings);
        $response = new Response(200, [], '{"ok":true}');
        $handler = static fn (Request $request, array $options) => Create::promiseFor($response);

        $wrapped = $middleware($handler);
        $result = $wrapped(new Request('POST', 'https://api.shopware.com/plugins', [], '{"search":"foo"}'), [])->wait();

        static::assertSame($response, $result);
    }

    public function testRejectedPromiseIsLoggedAsErrorAndPropagated(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('info');
        $logger->expects(static::once())->method('error');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(true);

        $middleware = new StoreClientLoggingMiddleware($logger, new HttpRequestLogFormatter(), $settings);
        $exception = new \RuntimeException('Connection refused');
        $handler = static fn (Request $request, array $options) => Create::rejectionFor($exception);

        $wrapped = $middleware($handler);

        $this->expectException(\RuntimeException::class);
        $wrapped(new Request('GET', 'https://api.shopware.com/plugins'), [])->wait();
    }
}
