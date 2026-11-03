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
namespace MuckiLogPlugin\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

use MuckiLogPlugin\Services\LegacyStoreClientLoggingMiddleware;
use MuckiLogPlugin\Services\StoreClientLoggingMiddleware;

/**
 * Nachbildungen der beiden Shopware-Signaturen des Store-MiddlewareInterface.
 */
interface HandlerStyleMiddlewareFixture
{
    public function __invoke(callable $handler): callable;
}

interface ResponseMapperMiddlewareFixture
{
    public function __invoke(ResponseInterface $response): ResponseInterface;
}

class StoreClientMiddlewareCompilerPassTest extends TestCase
{
    public function testHandlerSignatureTagsHandlerMiddleware(): void
    {
        $container = $this->createContainer();

        (new StoreClientMiddlewareCompilerPass(HandlerStyleMiddlewareFixture::class))->process($container);

        $this->assertTagged($container, StoreClientLoggingMiddleware::class);
        static::assertFalse($container->hasDefinition(LegacyStoreClientLoggingMiddleware::class));
    }

    public function testResponseMapperSignatureTagsLegacyMiddleware(): void
    {
        $container = $this->createContainer();

        (new StoreClientMiddlewareCompilerPass(ResponseMapperMiddlewareFixture::class))->process($container);

        $this->assertTagged($container, LegacyStoreClientLoggingMiddleware::class);
        static::assertFalse($container->hasDefinition(StoreClientLoggingMiddleware::class));
    }

    public function testMissingInterfaceRemovesBothMiddlewares(): void
    {
        $container = $this->createContainer();

        (new StoreClientMiddlewareCompilerPass('MuckiLogPlugin\DoesNotExist\MiddlewareInterface'))->process($container);

        static::assertFalse($container->hasDefinition(StoreClientLoggingMiddleware::class));
        static::assertFalse($container->hasDefinition(LegacyStoreClientLoggingMiddleware::class));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition(StoreClientLoggingMiddleware::class, new Definition(StoreClientLoggingMiddleware::class));
        $container->setDefinition(LegacyStoreClientLoggingMiddleware::class, new Definition(LegacyStoreClientLoggingMiddleware::class));

        return $container;
    }

    private function assertTagged(ContainerBuilder $container, string $serviceId): void
    {
        $definition = $container->getDefinition($serviceId);

        static::assertTrue($definition->hasTag(StoreClientMiddlewareCompilerPass::STORE_CLIENT_TAG));
        static::assertTrue($definition->hasTag(StoreClientMiddlewareCompilerPass::FRW_CLIENT_TAG));
    }
}
