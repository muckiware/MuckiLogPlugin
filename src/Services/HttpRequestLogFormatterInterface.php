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

interface HttpRequestLogFormatterInterface
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
    ): string;

    /**
     * @param array<array-key, mixed> $headers
     */
    public function formatResponse(string $source, int $statusCode, array $headers, ?string $body): string;

    public function formatError(string $source, string $method, string $url, \Throwable $exception): string;
}
