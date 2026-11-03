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

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use MuckiLogPlugin\Core\Defaults;
use MuckiLogPlugin\Services\HttpRequestLogFormatterInterface;

final class LoggingResponse implements ResponseInterface
{
    private bool $logged = false;

    public function __construct(
        private readonly ResponseInterface $response,
        private readonly LoggerInterface $logger,
        private readonly HttpRequestLogFormatterInterface $formatter,
        private readonly string $source
    ) {
    }

    public function getWrappedResponse(): ResponseInterface
    {
        return $this->response;
    }

    public function getStatusCode(): int
    {
        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        try {
            $content = $this->response->getContent($throw);
        } catch (\Throwable $exception) {
            $this->logError($exception);
            throw $exception;
        }

        $this->logResponse($content);

        return $content;
    }

    /**
     * @return array<mixed>
     */
    public function toArray(bool $throw = true): array
    {
        $this->getContent($throw);

        return $this->response->toArray($throw);
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    private function logResponse(string $content): void
    {
        if ($this->logged) {
            return;
        }
        $this->logged = true;

        $this->logger->info(
            $this->formatter->formatResponse(
                $this->source,
                $this->response->getStatusCode(),
                $this->response->getHeaders(false),
                $content
            ),
            [Defaults::OUTBOUND_HTTP_LOG_VENDOR, Defaults::OUTBOUND_HTTP_LOG_PLUGIN]
        );
    }

    private function logError(\Throwable $exception): void
    {
        if ($this->logged) {
            return;
        }
        $this->logged = true;

        $this->logger->error(
            $this->formatter->formatError(
                $this->source,
                (string) ($this->response->getInfo('http_method') ?? ''),
                (string) ($this->response->getInfo('url') ?? ''),
                $exception
            ),
            [Defaults::OUTBOUND_HTTP_LOG_VENDOR, Defaults::OUTBOUND_HTTP_LOG_PLUGIN]
        );
    }
}
