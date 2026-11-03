<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
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
        if ($subEntity !== null && isset($context['linkedEntity']) && \is_string($context['linkedEntity'])) {
            return $this->extractMapping($result, $subEntity, $context);
        }

        $definition = $this->registry->getByEntityName($result->getEntityName());
        $changes = [];

        foreach ($this->pairs($result) as $name => [$old, $new]) {
            $field = $this->findField($definition, $name);
            if ($field === null || $this->isSkipped($field, $subEntity)) {
                continue;
            }
            $property = $field->getPropertyName();

            $changes[] = $this->build($property, $field, $subEntity, $context, $old, $new);
        }

        return $changes;
    }

    /**
     * Mapping rows consist only of FKs (Ruling R14): log the assignment itself.
     *
     * @param array<string, mixed> $context
     *
     * @return list<FieldChange>
     */
    private function extractMapping(EntityWriteResult $result, string $subEntity, array $context): array
    {
        $linkedId = $context['linkedId'] ?? null;
        $field = (string) $context['linkedEntity'];

        return match (ChangeAction::fromOperation($result->getOperation())) {
            ChangeAction::Insert => [new FieldChange($field, $subEntity, $context, null, $linkedId, false)],
            ChangeAction::Delete => [new FieldChange($field, $subEntity, $context, $linkedId, null, false)],
            default => [],
        };
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

    /**
     * Skips technical columns, associations/inheritance join columns and
     * indexer-maintained fields (WriteProtected/Runtime), see Ruling R11.
     */
    private function isSkipped(Field $field, ?string $subEntity): bool
    {
        $property = $field->getPropertyName();
        if (\in_array($property, self::SKIPPED, true) || str_ends_with($property, 'VersionId')) {
            return true;
        }
        if ($field instanceof AssociationField || $field->is(WriteProtected::class) || $field->is(Runtime::class)) {
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

        $masked = false;
        $old = $this->masker->maskValue($this->normalizer->normalize($old), $masked);
        $new = $this->masker->maskValue($this->normalizer->normalize($new), $masked);

        return new FieldChange($property, $subEntity, $context, $old, $new, $masked);
    }
}
