<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2026 by muckiware
 *
 */
namespace MuckiLogPlugin\tests\TestCaseBase;

use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

/**
 * Skips tests that depend on shop entities (product, order, customer, ...) when the running
 * platform does not register them, e.g. Muckidrive, which ships Shopware Core without Checkout.
 */
trait RequiresEntityDefinitions
{
    protected function skipIfEntitiesMissing(string ...$entityNames): void
    {
        $registry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        $missing = array_values(array_filter(
            $entityNames,
            static fn (string $entityName): bool => !$registry->has($entityName)
        ));

        if ($missing !== []) {
            static::markTestSkipped(sprintf('Entity definition(s) not available: %s', implode(', ', $missing)));
        }
    }
}
