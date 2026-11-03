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
 * cascade-deleted children are not recorded.
 *
 * The root's before-values are kept only for label resolution, never persisted.
 */
final class CommitBuilder
{
    /** @var array<string, array{name: string, id: string, action: ChangeAction, rootWritten: bool, changes: list<FieldChange>, before: array<string, mixed>}> */
    private array $entries = [];

    /**
     * @param list<FieldChange>    $changes
     * @param array<string, mixed> $rootBeforeValues
     */
    public function add(RootReference $root, ChangeAction $action, array $changes, array $rootBeforeValues = []): void
    {
        $key = $root->entityName . '|' . $root->entityId;
        $entry = $this->entries[$key] ?? [
            'name' => $root->entityName,
            'id' => $root->entityId,
            'action' => ChangeAction::Update,
            'rootWritten' => false,
            'changes' => [],
            'before' => [],
        ];

        if ($root->subEntity === null) {
            $entry['rootWritten'] = true;
            $entry['action'] = $this->stronger($entry['action'], $action);
            if ($rootBeforeValues !== []) {
                $entry['before'] = $rootBeforeValues;
            }
        }

        if ($entry['action'] === ChangeAction::Delete) {
            // Root delete: keep only the root's own changes, drop cascaded children.
            $entry['changes'] = array_values(array_filter(
                $entry['changes'],
                static fn (FieldChange $c): bool => $c->subEntity === null,
            ));
            if ($root->subEntity !== null) {
                $changes = [];
            }
        }

        array_push($entry['changes'], ...$changes);
        $this->entries[$key] = $entry;
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
