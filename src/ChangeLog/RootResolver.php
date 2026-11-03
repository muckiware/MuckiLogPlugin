<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\Struct\ParentLink;
use MuckiLogPlugin\ChangeLog\Struct\RootReference;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Maps a write result of a (sub-)entity to its topmost tracked root entity.
 */
final class RootResolver
{
    /** @var array<string, ?string> */
    private array $lookups = [];

    public function __construct(
        private readonly EntityGraph $graph,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @param list<string> $tracked
     */
    public function resolve(EntityWriteResult $result, array $tracked): ?RootReference
    {
        $entity = $result->getEntityName();
        if (!$this->graph->isRelevant($entity, $tracked)) {
            return null;
        }

        $hop = $this->firstHop($result, $tracked);
        $root = $hop === null ? null : $this->topmostTracked($hop[0]->parentEntity, $hop[1], $tracked);
        if ($hop !== null && $root !== null) {
            return new RootReference($root[0], $root[1], $entity, $this->context($result, $hop[0]));
        }

        $ownId = $this->ownId($result);
        if ($ownId === null || !\in_array($entity, $tracked, true)) {
            return null;
        }

        return new RootReference($entity, $ownId, null, []);
    }

    /**
     * @param list<string> $tracked
     *
     * @return array{0: ParentLink, 1: string}|null
     */
    private function firstHop(EntityWriteResult $result, array $tracked): ?array
    {
        foreach ($this->graph->getParentLinks($result->getEntityName()) as $link) {
            if (!$this->graph->isRelevant($link->parentEntity, $tracked)) {
                continue;
            }
            $parentId = $this->valueOf($result, $link->fkPropertyName, $link->fkStorageName);
            if ($parentId !== null) {
                return [$link, $parentId];
            }
        }

        return null;
    }

    /**
     * @param list<string> $tracked
     *
     * @return array{0: string, 1: string}|null
     */
    private function topmostTracked(string $entity, string $id, array $tracked): ?array
    {
        $root = null;
        $visited = [];
        $current = [$entity, $id];

        while ($current !== null && !isset($visited[$current[0]])) {
            $visited[$current[0]] = true;
            if (\in_array($current[0], $tracked, true)) {
                $root = $current;
            }
            $current = $this->nextHop($current[0], $current[1], $tracked);
        }

        return $root;
    }

    /**
     * @param list<string> $tracked
     *
     * @return array{0: string, 1: string}|null
     */
    private function nextHop(string $entity, string $id, array $tracked): ?array
    {
        foreach ($this->graph->getParentLinks($entity) as $link) {
            if (!$this->graph->isRelevant($link->parentEntity, $tracked)) {
                continue;
            }
            $parentId = $this->lookup($entity, $link->fkStorageName, $id);
            if ($parentId !== null) {
                return [$link->parentEntity, $parentId];
            }
        }

        return null;
    }

    private function lookup(string $table, string $column, string $id): ?string
    {
        $key = $table . '.' . $column . '.' . $id;
        if (\array_key_exists($key, $this->lookups)) {
            return $this->lookups[$key];
        }

        $sql = \sprintf('SELECT LOWER(HEX(`%s`)) FROM `%s` WHERE id = UNHEX(:id)', $column, $table);
        $params = ['id' => $id];
        if ($this->graph->isVersionAware($table)) {
            $sql .= ' AND version_id = UNHEX(:versionId)';
            $params['versionId'] = Defaults::LIVE_VERSION;
        }

        $value = $this->connection->fetchOne($sql, $params);

        return $this->lookups[$key] = \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function context(EntityWriteResult $result, ParentLink $link): array
    {
        return match ($link->kind) {
            ParentLink::KIND_TRANSLATION => ['languageId' => $this->valueOf($result, 'languageId', 'language_id')],
            ParentLink::KIND_MAPPING => $this->mappingContext($result, $link),
            default => ['id' => $this->ownId($result)],
        };
    }

    /**
     * @return array{linkedEntity: ?string, linkedId: ?string}
     */
    private function mappingContext(EntityWriteResult $result, ParentLink $chosen): array
    {
        foreach ($this->graph->getParentLinks($result->getEntityName()) as $link) {
            if ($link->fkStorageName !== $chosen->fkStorageName) {
                return [
                    'linkedEntity' => $link->parentEntity,
                    'linkedId' => $this->valueOf($result, $link->fkPropertyName, $link->fkStorageName),
                ];
            }
        }

        return ['linkedEntity' => null, 'linkedId' => null];
    }

    private function ownId(EntityWriteResult $result): ?string
    {
        $primaryKey = $result->getPrimaryKey();

        return $this->toHex(\is_array($primaryKey) ? ($primaryKey['id'] ?? null) : $primaryKey);
    }

    private function valueOf(EntityWriteResult $result, string $property, string $storage): ?string
    {
        $primaryKey = $result->getPrimaryKey();

        return $this->toHex(\is_array($primaryKey) ? ($primaryKey[$property] ?? null) : null)
            ?? $this->toHex($result->getPayload()[$property] ?? null)
            ?? $this->toHex($result->getChangeSet()?->getBefore($storage));
    }

    private function toHex(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }
        if (\strlen($value) === 16) {
            return Uuid::fromBytesToHex($value);
        }

        return Uuid::isValid($value) ? strtolower($value) : null;
    }
}
