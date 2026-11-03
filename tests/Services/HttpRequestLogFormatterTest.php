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

class HttpRequestLogFormatterTest extends TestCase
{
    public function testFormatRequestContainsAllParts(): void
    {
        $formatter = new HttpRequestLogFormatter();

        $message = $formatter->formatRequest(
            'http_client',
            'POST',
            'https://example.com/api',
            ['foo' => 'bar'],
            ['Authorization' => 'Bearer secret'],
            '{"key":"value"}'
        );

        static::assertStringContainsString('[http_client]', $message);
        static::assertStringContainsString('POST', $message);
        static::assertStringContainsString('https://example.com/api', $message);
        static::assertStringContainsString('foo: bar', $message);
        static::assertStringContainsString('Authorization: Bearer secret', $message);
        static::assertStringContainsString('{"key":"value"}', $message);
    }

    public function testFormatRequestWithoutBodyUsesPlaceholder(): void
    {
        $formatter = new HttpRequestLogFormatter();

        $message = $formatter->formatRequest('http_client', 'GET', 'https://example.com', [], [], null);

        static::assertStringContainsString('(kein Body)', $message);
        static::assertStringContainsString('(none)', $message);
    }

    public function testFormatResponseContainsStatusAndBody(): void
    {
        $formatter = new HttpRequestLogFormatter();

        $message = $formatter->formatResponse('store_client', 200, ['content-type' => ['application/json']], '{"ok":true}');

        static::assertStringContainsString('[store_client]', $message);
        static::assertStringContainsString('200', $message);
        static::assertStringContainsString('content-type: application/json', $message);
        static::assertStringContainsString('{"ok":true}', $message);
    }

    public function testFormatErrorContainsExceptionDetails(): void
    {
        $formatter = new HttpRequestLogFormatter();
        $exception = new \RuntimeException('Connection refused');

        $message = $formatter->formatError('webhook', 'POST', 'https://example.com/hook', $exception);

        static::assertStringContainsString('[webhook]', $message);
        static::assertStringContainsString('POST', $message);
        static::assertStringContainsString('https://example.com/hook', $message);
        static::assertStringContainsString('RuntimeException', $message);
        static::assertStringContainsString('Connection refused', $message);
    }
}
