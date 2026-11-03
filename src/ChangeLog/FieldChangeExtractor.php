<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;

final class FieldChangeExtractor
{
    private const SKIPPED = ['id', 'versionId', 'createdAt', 'updatedAt'];

    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly ValueNormalizer $normalizer,
        private readonly SensitiveFieldMasker $masker,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<FieldChange>
     */
    public function extract(EntityWriteResult $result, ?string $subEntity, array $context): array
    {
        $definition = $this->registry->getByEntityName($result->getEntityName());
        $changes = [];

        foreach ($this->pairs($result) as $name => [$old, $new]) {
            $field = $this->findField($definition, $name);
            $property = $field?->getPropertyName() ?? $name;
            if ($this->isSkipped($property, $field, $subEntity)) {
                continue;
            }

            $changes[] = $this->build($property, $field, $subEntity, $context, $old, $new);
        }

        return $changes;
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function pairs(EntityWriteResult $result): array
    {
        $changeSet = $result->getChangeSet();
        $action = ChangeAction::fromOperation($result->getOperation());
        $pairs = [];

        if ($action === ChangeAction::Delete) {
            foreach ($changeSet?->getBefore(null) ?? [] as $name => $before) {
                $pairs[(string) $name] = [$before, null];
            }

            return $pairs;
        }

        if ($action === ChangeAction::Update && $changeSet !== null) {
            foreach ($changeSet->getAfter(null) as $name => $after) {
                $pairs[(string) $name] = [$changeSet->getBefore((string) $name), $after];
            }

            return $pairs;
        }

        foreach ($result->getPayload() as $name => $value) {
            $pairs[(string) $name] = [null, $value];
        }

        return $pairs;
    }

    private function findField(EntityDefinition $definition, string $name): ?Field
    {
        $fields = $definition->getFields();

        return $fields->get($name) ?? $fields->getByStorageName($name);
    }

    private function isSkipped(string $property, ?Field $field, ?string $subEntity): bool
    {
        if (\in_array($property, self::SKIPPED, true) || str_ends_with($property, 'VersionId')) {
            return true;
        }

        return $subEntity !== null && $field instanceof FkField;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function build(string $property, ?Field $field, ?string $subEntity, array $context, mixed $old, mixed $new): FieldChange
    {
        if ($this->masker->isSensitive($property, $field)) {
            return new FieldChange($property, $subEntity, $context, SensitiveFieldMasker::MASK, SensitiveFieldMasker::MASK, true);
        }

        return new FieldChange(
            $property,
            $subEntity,
            $context,
            $this->normalizer->normalize($old),
            $this->normalizer->normalize($new),
        );
    }
}
