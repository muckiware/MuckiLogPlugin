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

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

use MuckiLogPlugin\Core\Defaults;

trait GuzzleLoggingTrait
{
    private function logGuzzleRequest(RequestInterface $request): void
    {
        $query = [];
        parse_str($request->getUri()->getQuery(), $query);

        $this->logger->info(
            $this->formatter->formatRequest(
                $this->source,
                $request->getMethod(),
                (string) $request->getUri(),
                $query,
                $request->getHeaders(),
                $this->readStream($request->getBody())
            ),
            [Defaults::OUTBOUND_HTTP_LOG_VENDOR, Defaults::OUTBOUND_HTTP_LOG_PLUGIN]
        );
    }

    private function logGuzzleResponse(ResponseInterface $response): void
    {
        $this->logger->info(
            $this->formatter->formatResponse(
                $this->source,
                $response->getStatusCode(),
                $response->getHeaders(),
                $this->readStream($response->getBody())
            ),
            [Defaults::OUTBOUND_HTTP_LOG_VENDOR, Defaults::OUTBOUND_HTTP_LOG_PLUGIN]
        );
    }

    private function logGuzzleError(RequestInterface $request, mixed $reason): void
    {
        $exception = $reason instanceof \Throwable ? $reason : new \RuntimeException((string) $reason);

        $this->logger->error(
            $this->formatter->formatError($this->source, $request->getMethod(), (string) $request->getUri(), $exception),
            [Defaults::OUTBOUND_HTTP_LOG_VENDOR, Defaults::OUTBOUND_HTTP_LOG_PLUGIN]
        );
    }

    /**
     * Liest den Stream fuer das Log und spult ihn danach zurueck, damit der
     * eigentliche Aufrufer den Body weiterhin konsumieren kann. Nicht
     * zurueckspulbare Streams werden zum Schutz der Anfrage nicht gelesen.
     */
    private function readStream(StreamInterface $stream): ?string
    {
        if (!$stream->isSeekable() || $stream->getSize() === 0) {
            return null;
        }

        $content = (string) $stream;
        $stream->rewind();

        return $content === '' ? null : $content;
    }
}
