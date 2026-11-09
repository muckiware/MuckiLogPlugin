<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\ChangeLogCommit;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use MuckiLogPlugin\ChangeLog\Struct\RootReference;

/**
 * Groups the field changes of one write event into one commit per root record.
 *
 * Stateful: NOT registered as a shared service. Instantiate with
 * `new CommitBuilder()` once per event.
 *
 * Action precedence when the root itself is written: Delete > Insert > Update.
 * Sub-entity-only changes yield Update. Update commits without changes are
 * dropped; Insert/Delete commits are kept. For a deleted root, changes of
 * cascade-deleted children are not recorded, except its translations.
 *
 * The root's before-values are kept only for label resolution, never persisted.
 */
final class CommitBuilder
{
    /** @var array<string, array{name: string, id: string, action: ChangeAction, changes: list<FieldChange>, before: array<string, mixed>}> */
    private array $entries = [];

    /**
     * @param list<FieldChange>    $changes
     * @param array<string, mixed> $rootBeforeValues
     */
    public function add(RootReference $root, ChangeAction $action, array $changes, array $rootBeforeValues = []): void
    {
        $key = $root->entityName . '|' . $root->entityId;
        $entry = $this->entries[$key] ?? $this->newEntry($root);

        if ($root->subEntity === null) {
            $entry['action'] = $this->stronger($entry['action'], $action);
            if ($rootBeforeValues !== []) {
                $entry['before'] = $rootBeforeValues;
            }
        }

        if ($entry['action'] === ChangeAction::Delete) {
            $entry['changes'] = $this->ownChanges($entry['changes']);
            $changes = $this->keptOnDelete($root->subEntity) ? $changes : [];
        }

        array_push($entry['changes'], ...$changes);
        $this->entries[$key] = $entry;
    }

    /**
     * @return array{name: string, id: string, action: ChangeAction, changes: list<FieldChange>, before: array<string, mixed>}
     */
    private function newEntry(RootReference $root): array
    {
        return [
            'name' => $root->entityName,
            'id' => $root->entityId,
            'action' => ChangeAction::Update,
            'changes' => [],
            'before' => [],
        ];
    }

    /**
     * Root delete: keep only the root's own and translation changes, drop cascaded children.
     *
     * @param list<FieldChange> $changes
     *
     * @return list<FieldChange>
     */
    private function ownChanges(array $changes): array
    {
        return array_values(array_filter(
            $changes,
            fn (FieldChange $c): bool => $this->keptOnDelete($c->subEntity),
        ));
    }

    /**
     * Root row and its translations belong to "what was deleted" (Ruling R10).
     */
    private function keptOnDelete(?string $subEntity): bool
    {
        return $subEntity === null || str_ends_with($subEntity, '_translation');
    }

    /**
     * @return list<ChangeLogCommit>
     */
    public function build(): array
    {
        $commits = [];
        foreach ($this->entries as $e) {
            if ($e['action'] === ChangeAction::Update && $e['changes'] === []) {
                continue;
            }
            $commits[] = new ChangeLogCommit($e['name'], $e['id'], $e['action'], $e['changes'], $e['before']);
        }

        return $commits;
    }

    private function stronger(ChangeAction $current, ChangeAction $incoming): ChangeAction
    {
        return $this->rank($incoming) > $this->rank($current) ? $incoming : $current;
    }

    private function rank(ChangeAction $action): int
    {
        return match ($action) {
            ChangeAction::Delete => 2,
            ChangeAction::Insert => 1,
            ChangeAction::Update => 0,
        };
    }
}
