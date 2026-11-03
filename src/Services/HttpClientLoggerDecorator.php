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

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

use MuckiLogPlugin\Core\Defaults;
use MuckiLogPlugin\Services\Http\LoggingResponse;

final class HttpClientLoggerDecorator implements HttpClientInterface
{
    private const SOURCE = 'http_client';

    public function __construct(
        private HttpClientInterface $client,
        private readonly LoggerInterface $logger,
        private readonly HttpRequestLogFormatterInterface $formatter,
        private readonly SettingsInterface $pluginSettings
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->pluginSettings->isHttpLoggingEnabled()) {
            return $this->client->request($method, $url, $options);
        }

        $this->logRequest($method, $url, $options);

        return new LoggingResponse(
            $this->client->request($method, $url, $options),
            $this->logger,
            $this->formatter,
            self::SOURCE
        );
    }

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($this->unwrap($responses), $timeout);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);

        return $clone;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function logRequest(string $method, string $url, array $options): void
    {
        $this->logger->info(
            $this->formatter->formatRequest(
                self::SOURCE,
                $method,
                $url,
                $this->extractQuery($url, $options),
                is_array($options['headers'] ?? null) ? $options['headers'] : [],
                $this->extractBody($options)
            ),
            [Defaults::OUTBOUND_HTTP_LOG_VENDOR, Defaults::OUTBOUND_HTTP_LOG_PLUGIN]
        );
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function extractQuery(string $url, array $options): array
    {
        $urlQuery = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $urlQuery);

        $optionsQuery = is_array($options['query'] ?? null) ? $options['query'] : [];

        return array_merge($urlQuery, $optionsQuery);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function extractBody(array $options): ?string
    {
        if (isset($options['json'])) {
            return json_encode($options['json'], JSON_THROW_ON_ERROR);
        }

        if (is_string($options['body'] ?? null)) {
            return $options['body'];
        }

        if (is_array($options['body'] ?? null)) {
            return http_build_query($options['body']);
        }

        return null;
    }

    /**
     * @param ResponseInterface|iterable<ResponseInterface> $responses
     * @return ResponseInterface|iterable<ResponseInterface>
     */
    private function unwrap(ResponseInterface|iterable $responses): ResponseInterface|iterable
    {
        if ($responses instanceof LoggingResponse) {
            return $responses->getWrappedResponse();
        }

        if (!is_iterable($responses)) {
            return $responses;
        }

        $unwrapped = [];
        foreach ($responses as $response) {
            $unwrapped[] = $response instanceof LoggingResponse ? $response->getWrappedResponse() : $response;
        }

        return $unwrapped;
    }
}
