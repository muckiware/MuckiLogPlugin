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

use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

use MuckiLogPlugin\Services\Http\GuzzleLoggingTrait;

/**
 * Store-/FRW-Client-Middleware fuer Shopware-Versionen, deren
 * MiddlewareInterface noch die Response-Mapper-Signatur
 * __invoke(ResponseInterface): ResponseInterface hat (6.6.x, fruehe 6.7.x).
 * Ein Response-Mapper sieht den Request nicht, daher werden nur Responses
 * geloggt. Aktivierung erfolgt ueber den StoreClientMiddlewareCompilerPass.
 */
final class LegacyStoreClientLoggingMiddleware
{
    use GuzzleLoggingTrait;

    private const SOURCE = 'store_client';

    private readonly string $source;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpRequestLogFormatterInterface $formatter,
        private readonly SettingsInterface $pluginSettings
    ) {
        $this->source = self::SOURCE;
    }

    public function __invoke(ResponseInterface $response): ResponseInterface
    {
        if ($this->pluginSettings->isHttpLoggingEnabled()) {
            $this->logGuzzleResponse($response);
        }

        return $response;
    }
}
