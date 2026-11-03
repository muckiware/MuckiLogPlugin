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

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LegacyStoreClientLoggingMiddlewareTest extends TestCase
{
    public function testResponseIsReturnedUnloggedWhenFeatureDisabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())->method('info');

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(false);

        $middleware = new LegacyStoreClientLoggingMiddleware($logger, new HttpRequestLogFormatter(), $settings);
        $response = new Response(200, [], '{"ok":true}');

        static::assertSame($response, $middleware($response));
    }

    public function testResponseIsLoggedAndBodyStaysReadableWhenFeatureEnabled(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())
            ->method('info')
            ->with(static::stringContains('{"ok":true}'));

        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('isHttpLoggingEnabled')->willReturn(true);

        $middleware = new LegacyStoreClientLoggingMiddleware($logger, new HttpRequestLogFormatter(), $settings);
        $response = new Response(200, [], '{"ok":true}');

        $result = $middleware($response);

        static::assertSame($response, $result);
        static::assertSame('{"ok":true}', $result->getBody()->getContents());
    }
}
