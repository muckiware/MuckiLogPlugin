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

use Shopware\Core\Framework\Store\Services\MiddlewareInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

use MuckiLogPlugin\Services\LegacyStoreClientLoggingMiddleware;
use MuckiLogPlugin\Services\StoreClientLoggingMiddleware;

/**
 * Shopware hat die Signatur des (internen) Store-MiddlewareInterface innerhalb
 * von 6.7 geaendert: frueher Response-Mapper __invoke(ResponseInterface),
 * jetzt Guzzle-Handler-Middleware __invoke(callable). Dieser Pass taggt die
 * passende Implementierung und entfernt die andere.
 */
final class StoreClientMiddlewareCompilerPass implements CompilerPassInterface
{
    public const STORE_CLIENT_TAG = 'shopware.store_client.middleware';
    public const FRW_CLIENT_TAG = 'shopware.frw_client.middleware';

    public function __construct(
        private readonly string $interfaceClass = MiddlewareInterface::class
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $handlerStyle = $this->usesHandlerSignature();
        if ($handlerStyle === null) {
            $container->removeDefinition(StoreClientLoggingMiddleware::class);
            $container->removeDefinition(LegacyStoreClientLoggingMiddleware::class);

            return;
        }

        $activeId = $handlerStyle ? StoreClientLoggingMiddleware::class : LegacyStoreClientLoggingMiddleware::class;
        $inactiveId = $handlerStyle ? LegacyStoreClientLoggingMiddleware::class : StoreClientLoggingMiddleware::class;

        $container->removeDefinition($inactiveId);
        if (!$container->hasDefinition($activeId)) {
            return;
        }

        $container->getDefinition($activeId)
            ->addTag(self::STORE_CLIENT_TAG)
            ->addTag(self::FRW_CLIENT_TAG);
    }

    /**
     * true = Handler-Signatur (callable), false = Response-Mapper,
     * null = Interface fehlt oder Signatur unbekannt.
     */
    private function usesHandlerSignature(): ?bool
    {
        if (!interface_exists($this->interfaceClass) || !method_exists($this->interfaceClass, '__invoke')) {
            return null;
        }

        $parameter = (new \ReflectionMethod($this->interfaceClass, '__invoke'))->getParameters()[0] ?? null;
        $type = $parameter?->getType();
        if (!$type instanceof \ReflectionNamedType) {
            return null;
        }

        return match ($type->getName()) {
            'callable' => true,
            'Psr\Http\Message\ResponseInterface' => false,
            default => null,
        };
    }
}
