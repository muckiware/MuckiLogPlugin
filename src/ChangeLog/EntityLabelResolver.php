<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

/**
 * Resolves a human readable label for root records (one query per entity type).
 */
final class EntityLabelResolver
{
    private const GENERIC_FIELDS = ['name', 'title', 'label', 'productNumber', 'orderNumber', 'customerNumber'];

    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        Connection $connection,
    ) {
        // Reserved for fallbacks; labels are read through the DAL repository.
    }

    /**
     * @param array<string, array<string, mixed>> $beforeValuesById values from the ChangeSet, used if the record cannot be loaded
     *
     * @return array<string, string> id => label
     */
    public function resolveMany(string $entityName, array $beforeValuesById, Context $context): array
    {
        if ($beforeValuesById === []) {
            return [];
        }

        $loaded = $this->load($entityName, array_map('strval', array_keys($beforeValuesById)), $context);
        $labels = [];
        foreach ($beforeValuesById as $id => $before) {
            $id = (string) $id;
            $labels[$id] = (isset($loaded[$id]) ? $this->fromEntity($entityName, $loaded[$id]) : null)
                ?? $this->fromValues($entityName, $before)
                ?? $id;
        }

        return $labels;
    }

    /**
     * @param list<string> $ids
     *
     * @return array<string, Entity>
     */
    private function load(string $entityName, array $ids, Context $context): array
    {
        try {
            $entities = $this->registry->getRepository($entityName)->search(new Criteria($ids), $context)->getEntities();
        } catch (\Throwable) {
            return [];
        }

        $result = [];
        foreach ($entities as $entity) {
            if ($entity instanceof Entity) {
                $result[$entity->getUniqueIdentifier()] = $entity;
            }
        }

        return $result;
    }

    private function fromEntity(string $entityName, Entity $entity): ?string
    {
        $read = fn (string $field): mixed => $this->read($entity, $field);

        return $this->compose($entityName, $read);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function fromValues(string $entityName, array $values): ?string
    {
        $read = static function (string $field) use ($values): mixed {
            $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $field));

            return $values[$field] ?? $values[$snake] ?? null;
        };

        return $this->compose($entityName, $read);
    }

    private function compose(string $entityName, callable $read): ?string
    {
        if ($entityName === 'customer') {
            $label = $this->customerLabel($read);
            if ($label !== null) {
                return $label;
            }
        }

        $fields = $entityName === 'order' ? ['orderNumber'] : self::GENERIC_FIELDS;
        foreach ($fields as $field) {
            $value = $read($field);
            if (\is_scalar($value) && (string) $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function customerLabel(callable $read): ?string
    {
        $name = trim(\sprintf('%s %s', $this->str($read('firstName')), $this->str($read('lastName'))));
        $number = $this->str($read('customerNumber'));

        if ($name === '') {
            return $number !== '' ? $number : null;
        }

        return $number !== '' ? \sprintf('%s (%s)', $name, $number) : $name;
    }

    private function str(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    private function read(Entity $entity, string $field): mixed
    {
        $translated = $entity->getTranslation($field);
        if ($translated !== null) {
            return $translated;
        }

        return $entity->has($field) ? $entity->get($field) : null;
    }
}
