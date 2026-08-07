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

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class LoggerDecoratorCompilerPass implements CompilerPassInterface
{
    private const DECORATOR_ID = 'MuckiLogPlugin\Services\LoggerServiceDecorator';
    private const LOGGER_ID = 'logger';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::DECORATOR_ID)) {
            return;
        }

        $decoratorDeps = [];
        $this->collectTransitiveDeps(self::DECORATOR_ID, $container, $decoratorDeps);

        $this->replaceLoggerArguments($container, $decoratorDeps);
    }

    /**
     * Ersetzt id="logger" Injektionen durch den Decorator.
     */
    private function replaceLoggerArguments(ContainerBuilder $container, array $decoratorDeps): void
    {
        foreach ($container->getDefinitions() as $serviceId => $definition) {
            if ($serviceId === self::DECORATOR_ID || isset($decoratorDeps[$serviceId])) {
                continue;
            }

            foreach ($definition->getArguments() as $index => $argument) {
                if ($argument instanceof Reference && (string) $argument === self::LOGGER_ID) {
                    $definition->replaceArgument($index, new Reference(self::DECORATOR_ID));
                }
            }

            $methodCalls = $definition->getMethodCalls();
            $modified = false;
            foreach ($methodCalls as &$call) {
                [, $arguments] = $call;
                foreach ($arguments as $argIndex => $argument) {
                    if ($argument instanceof Reference && (string) $argument === self::LOGGER_ID) {
                        $arguments[$argIndex] = new Reference(self::DECORATOR_ID);
                        $modified = true;
                    }
                }
                $call[1] = $arguments;
            }
            unset($call);

            if ($modified) {
                $definition->setMethodCalls($methodCalls);
            }
        }
    }

    private function collectTransitiveDeps(string $serviceId, ContainerBuilder $container, array &$visited): void
    {
        $resolvedId = $this->resolveAlias($serviceId, $container);

        if (isset($visited[$resolvedId])) {
            return;
        }

        $visited[$resolvedId] = true;
        if ($resolvedId !== $serviceId) {
            $visited[$serviceId] = true;
        }

        if (!$container->hasDefinition($resolvedId)) {
            return;
        }

        $definition = $container->getDefinition($resolvedId);

        foreach ($definition->getArguments() as $argument) {
            if ($argument instanceof Reference) {
                $this->collectTransitiveDeps((string) $argument, $container, $visited);
            }
        }

        foreach ($definition->getMethodCalls() as [, $arguments]) {
            foreach ($arguments as $argument) {
                if ($argument instanceof Reference) {
                    $this->collectTransitiveDeps((string) $argument, $container, $visited);
                }
            }
        }
    }

    private function resolveAlias(string $serviceId, ContainerBuilder $container): string
    {
        while ($container->hasAlias($serviceId)) {
            $serviceId = (string) $container->getAlias($serviceId);
        }

        return $serviceId;
    }
}
