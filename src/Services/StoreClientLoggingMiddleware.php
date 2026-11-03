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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Store\Services\MiddlewareInterface;

use MuckiLogPlugin\Services\Http\GuzzleLoggingTrait;

/**
 * Guzzle-Middleware fuer den Store-/FRW-API-Client (shopware.store_client,
 * shopware.frw_client), registriert ueber deren offiziellen
 * Middleware-Tag-Mechanismus.
 */
final class StoreClientLoggingMiddleware implements MiddlewareInterface
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

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler) {
            if (!$this->pluginSettings->isHttpLoggingEnabled()) {
                return $handler($request, $options);
            }

            $this->logGuzzleRequest($request);

            return $handler($request, $options)->then(
                function (ResponseInterface $response) {
                    $this->logGuzzleResponse($response);

                    return $response;
                },
                function (mixed $reason) use ($request) {
                    $this->logGuzzleError($request, $reason);

                    return Create::rejectionFor($reason);
                }
            );
        };
    }
}
