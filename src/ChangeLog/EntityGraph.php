<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ParentLink;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;

/**
 * Static, definition-based graph of sub-entities (translations, owned children, mapping tables) to their parents.
 */
final class EntityGraph
{
    private const NEVER_RELEVANT = ['system_config', 'muwa_admin_change_log'];

    /** @var array<string, list<ParentLink>>|null */
    private ?array $childIndex = null;

    /** @var array<string, list<ParentLink>> */
    private array $links = [];

    /** @var array<string, bool> */
    private array $relevant = [];

    public function __construct(private readonly DefinitionInstanceRegistry $registry)
    {
    }

    /**
     * @return list<ParentLink>
     */
    public function getParentLinks(string $entity): array
    {
        if (isset($this->links[$entity])) {
            return $this->links[$entity];
        }
        if (!$this->registry->has($entity)) {
            return $this->links[$entity] = [];
        }

        $definition = $this->registry->getByEntityName($entity);

        return $this->links[$entity] = match (true) {
            $definition instanceof EntityTranslationDefinition => $this->translationLinks($definition),
            $definition instanceof MappingEntityDefinition => $this->mappingLinks($definition),
            default => $this->childIndex()[$entity] ?? [],
        };
    }

    /**
     * @param list<string> $tracked
     */
    public function isRelevant(string $entity, array $tracked): bool
    {
        $key = $entity . '|' . implode(',', $tracked);

        return $this->relevant[$key] ??= $this->reaches($entity, $tracked, []);
    }

    public function isVersionAware(string $entity): bool
    {
        return $this->registry->has($entity) && $this->registry->getByEntityName($entity)->isVersionAware();
    }

    /**
     * @param list<string> $tracked
     * @param array<string, true> $visited
     */
    private function reaches(string $entity, array $tracked, array $visited): bool
    {
        if (\in_array($entity, self::NEVER_RELEVANT, true) || isset($visited[$entity])) {
            return false;
        }
        if (\in_array($entity, $tracked, true)) {
            return true;
        }

        $visited[$entity] = true;
        foreach ($this->getParentLinks($entity) as $link) {
            if ($this->reaches($link->parentEntity, $tracked, $visited)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<ParentLink>
     */
    private function translationLinks(EntityTranslationDefinition $definition): array
    {
        $parent = $definition->getParentDefinition()->getEntityName();
        $field = $definition->getFields()->getByStorageName($parent . '_id');
        if (!$field instanceof FkField) {
            return [];
        }

        return [new ParentLink($parent, $field->getStorageName(), $field->getPropertyName(), ParentLink::KIND_TRANSLATION)];
    }

    /**
     * @return list<ParentLink>
     */
    private function mappingLinks(MappingEntityDefinition $definition): array
    {
        $links = [];
        foreach ($definition->getFields() as $field) {
            if (!$field instanceof FkField || $field instanceof ReferenceVersionField || $field instanceof VersionField) {
                continue;
            }
            $parent = $field->getReferenceDefinition()->getEntityName();
            $links[] = new ParentLink($parent, $field->getStorageName(), $field->getPropertyName(), ParentLink::KIND_MAPPING);
        }

        return $links;
    }

    /**
     * @return array<string, list<ParentLink>>
     */
    private function childIndex(): array
    {
        if ($this->childIndex !== null) {
            return $this->childIndex;
        }

        $this->childIndex = [];
        foreach ($this->registry->getDefinitions() as $parent) {
            foreach ($parent->getFields() as $field) {
                $this->indexChild($parent, $field);
            }
        }

        return $this->childIndex;
    }

    private function indexChild(EntityDefinition $parent, Field $field): void
    {
        if (!$this->isOwnedChildAssociation($field)) {
            return;
        }

        /** @var OneToManyAssociationField $field */
        $child = $field->getReferenceDefinition();
        if ($child->getEntityName() === $parent->getEntityName()
            || $child instanceof EntityTranslationDefinition
            || $child instanceof MappingEntityDefinition
        ) {
            return;
        }

        $fk = $child->getFields()->getByStorageName($field->getReferenceField());
        if (!$fk instanceof FkField) {
            return;
        }

        $this->childIndex[$child->getEntityName()][] = new ParentLink(
            $parent->getEntityName(),
            $fk->getStorageName(),
            $fk->getPropertyName(),
            ParentLink::KIND_CHILD
        );
    }

    private function isOwnedChildAssociation(Field $field): bool
    {
        if (!$field instanceof OneToManyAssociationField
            || $field instanceof ChildrenAssociationField
            || $field instanceof TranslationsAssociationField
            || $field->getLocalField() !== 'id'
        ) {
            return false;
        }

        $flag = $field->getFlag(CascadeDelete::class);

        return $flag instanceof CascadeDelete && $flag->isCloneRelevant();
    }
}
