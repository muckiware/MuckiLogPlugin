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

final class HttpRequestLogFormatter implements HttpRequestLogFormatterInterface
{
    /**
     * @param array<array-key, mixed> $query
     * @param array<array-key, mixed> $headers
     */
    public function formatRequest(
        string $source,
        string $method,
        string $url,
        array $query,
        array $headers,
        ?string $body
    ): string
    {
        return sprintf(
            "[%s] Request: %s %s\nQuery: %s\nHeaders: %s\nBody: %s",
            $source,
            $method,
            $url,
            $this->formatMap($query),
            $this->formatMap($headers),
            $body ?? '(kein Body)'
        );
    }

    /**
     * @param array<array-key, mixed> $headers
     */
    public function formatResponse(string $source, int $statusCode, array $headers, ?string $body): string
    {
        return sprintf(
            "[%s] Response: %d\nHeaders: %s\nBody: %s",
            $source,
            $statusCode,
            $this->formatMap($headers),
            $body ?? '(no Body)'
        );
    }

    public function formatError(string $source, string $method, string $url, \Throwable $exception): string
    {
        return sprintf(
            '[%s] Error at %s %s: %s: %s',
            $source,
            $method,
            $url,
            $exception::class,
            $exception->getMessage()
        );
    }

    /**
     * @param array<string, mixed> $map
     */
    private function formatMap(array $map): string
    {
        if ($map === []) {
            return '(none)';
        }

        $lines = [];
        foreach ($map as $name => $value) {
            $lines[] = $name . ': ' . (is_array($value) ? implode(', ', $value) : (string) $value);
        }

        return implode('; ', $lines);
    }
}
