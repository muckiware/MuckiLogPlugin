<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

/**
 * One logical change of a root record (all field changes of one save operation).
 *
 * $beforeValues (ChangeSet "before" of the root row) exist only for label
 * resolution (e.g. the name of a deleted record) and are never persisted.
 */
final readonly class ChangeLogCommit
{
    /**
     * @param list<FieldChange>    $changes
     * @param array<string, mixed> $beforeValues
     */
    public function __construct(
        private string $entityName,
        private string $entityId,
        private ChangeAction $action = ChangeAction::Update,
        private array $changes = [],
        private array $beforeValues = [],
        private ?string $label = null,
    ) {
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function getAction(): ChangeAction
    {
        return $this->action;
    }

    /**
     * @return list<FieldChange>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBeforeValues(): array
    {
        return $this->beforeValues;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }
}
