<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Uuid\Uuid;

final class FieldChangeExtractor
{
    private const SKIPPED = ['id', 'versionId', 'createdAt', 'updatedAt'];

    /**
     * Fields maintained automatically by the core (login, checkout, indexers), Ruling R20.
     */
    private const IGNORED_BY_ENTITY = [
        'customer' => ['lastLogin', 'lastPaymentMethodId', 'lastOrderDate', 'orderCount', 'orderTotalAmount', 'reviewCount'],
    ];

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
        $isUpdate = ChangeAction::fromOperation($result->getOperation()) === ChangeAction::Update;
        $changes = [];

        foreach ($this->pairs($result) as $name => [$old, $new]) {
            $field = $this->findField($definition, $name);
            if ($field === null || $this->isSkipped($result->getEntityName(), $field, $subEntity)) {
                continue;
            }
            $old = $this->normalize($field, $old);
            $new = $this->normalize($field, $new);
            if ($isUpdate && $this->isEquivalent($field, $old, $new)) {
                continue;
            }

            $changes[] = $this->build($field, $subEntity, $context, $old, $new);
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
     * indexer-maintained fields (WriteProtected/Runtime), see Ruling R11, and
     * automatically maintained fields (Ruling R20).
     */
    private function isSkipped(string $entityName, Field $field, ?string $subEntity): bool
    {
        $property = $field->getPropertyName();
        if (\in_array($property, self::SKIPPED, true) || str_ends_with($property, 'VersionId')) {
            return true;
        }
        if (\in_array($property, self::IGNORED_BY_ENTITY[$entityName] ?? [], true)) {
            return true;
        }
        if ($field instanceof AssociationField || $field->is(WriteProtected::class) || $field->is(Runtime::class)) {
            return true;
        }

        return $subEntity !== null && $field instanceof FkField;
    }

    /**
     * Binary ids are detected by field type, not by content (valid UTF-8 text may be 16 bytes long).
     */
    private function normalize(Field $field, mixed $value): mixed
    {
        // FkField covers ReferenceVersionField and VersionField (both extend it).
        if (($field instanceof IdField || $field instanceof FkField) && \is_string($value) && \strlen($value) === 16) {
            return Uuid::fromBytesToHex($value);
        }

        return $this->normalizer->normalize($value);
    }

    /**
     * MySQL normalizes JSON and the ChangeSet compares strings, so unchanged values can
     * show up as changed; compare canonical forms instead.
     */
    private function isEquivalent(Field $field, mixed $old, mixed $new): bool
    {
        return $this->canonical($field, $old) === $this->canonical($field, $new);
    }

    private function canonical(Field $field, mixed $value): string
    {
        if ($field instanceof BoolField && \in_array($value, [true, 1, '1', false, 0, '0'], true)) {
            return \in_array($value, [true, 1, '1'], true) ? 'true' : 'false';
        }
        if (($field instanceof IntField || $field instanceof FloatField) && is_numeric($value)) {
            $value = (float) $value;
        }

        return (string) json_encode($this->sortRecursive($value), \JSON_PRESERVE_ZERO_FRACTION);
    }

    private function sortRecursive(mixed $value): mixed
    {
        if (\is_int($value)) {
            return (float) $value;
        }
        if (!\is_array($value)) {
            return $value;
        }
        ksort($value);

        return array_map($this->sortRecursive(...), $value);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function build(Field $field, ?string $subEntity, array $context, mixed $old, mixed $new): FieldChange
    {
        $property = $field->getPropertyName();
        if ($this->masker->isSensitive($property, $field)) {
            return new FieldChange($property, $subEntity, $context, SensitiveFieldMasker::MASK, SensitiveFieldMasker::MASK, true);
        }

        $masked = false;
        $old = $this->masker->maskValue($old, $masked);
        $new = $this->masker->maskValue($new, $masked);

        return new FieldChange($property, $subEntity, $context, $old, $new, $masked);
    }
}
