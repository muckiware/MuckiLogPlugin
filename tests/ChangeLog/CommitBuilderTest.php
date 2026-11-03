<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\CommitBuilder;
use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use MuckiLogPlugin\ChangeLog\Struct\RootReference;
use PHPUnit\Framework\TestCase;

final class CommitBuilderTest extends TestCase
{
    private function root(string $id = 'p1', ?string $sub = null, string $name = 'product'): RootReference
    {
        return new RootReference($name, $id, $sub, []);
    }

    private function change(string $field, ?string $sub = null): FieldChange
    {
        return new FieldChange($field, $sub, [], 'a', 'b');
    }

    public function testGroupsRootAndSubEntitiesIntoOneUpdateCommit(): void
    {
        $b = new CommitBuilder();
        $b->add($this->root(), ChangeAction::Update, [$this->change('name')]);
        $b->add($this->root('p1', 'product_translation'), ChangeAction::Update, [$this->change('description', 'product_translation')]);
        $b->add($this->root('p1', 'product_price'), ChangeAction::Insert, [$this->change('price', 'product_price')]);

        $commits = $b->build();

        static::assertCount(1, $commits);
        static::assertSame(ChangeAction::Update, $commits[0]->getAction());
        static::assertCount(3, $commits[0]->getChanges());
        static::assertSame('product', $commits[0]->getEntityName());
        static::assertSame('p1', $commits[0]->getEntityId());
    }

    public function testSubEntityOnlyChangesAreUpdate(): void
    {
        $b = new CommitBuilder();
        $b->add($this->root('p1', 'product_price'), ChangeAction::Insert, [$this->change('price', 'product_price')]);

        $commits = $b->build();

        static::assertCount(1, $commits);
        static::assertSame(ChangeAction::Update, $commits[0]->getAction());
    }

    public function testRootInsertWinsOverSubUpdates(): void
    {
        $b = new CommitBuilder();
        $b->add($this->root('p1', 'product_translation'), ChangeAction::Update, [$this->change('name', 'product_translation')]);
        $b->add($this->root(), ChangeAction::Insert, [$this->change('productNumber')]);

        static::assertSame(ChangeAction::Insert, $b->build()[0]->getAction());
    }

    public function testRootDeleteWinsAndCascadedChildrenAreDropped(): void
    {
        $b = new CommitBuilder();
        $b->add($this->root('p1', 'product_price'), ChangeAction::Delete, [$this->change('price', 'product_price')]);
        $b->add($this->root(), ChangeAction::Delete, [$this->change('name')], ['name' => 'Shirt']);
        $b->add($this->root('p1', 'product_media'), ChangeAction::Delete, [$this->change('position', 'product_media')]);

        $commits = $b->build();

        static::assertCount(1, $commits);
        static::assertSame(ChangeAction::Delete, $commits[0]->getAction());
        static::assertCount(1, $commits[0]->getChanges());
        static::assertNull($commits[0]->getChanges()[0]->subEntity);
        static::assertSame(['name' => 'Shirt'], $commits[0]->getBeforeValues());
    }

    public function testTwoRootsKeepInsertionOrder(): void
    {
        $b = new CommitBuilder();
        $b->add($this->root('b'), ChangeAction::Update, [$this->change('x')]);
        $b->add($this->root('a'), ChangeAction::Update, [$this->change('x')]);
        $b->add($this->root('b'), ChangeAction::Update, [$this->change('y')]);
        $b->add($this->root('a', null, 'category'), ChangeAction::Update, [$this->change('x')]);

        $ids = array_map(static fn ($c) => $c->getEntityName() . ':' . $c->getEntityId(), $b->build());

        static::assertSame(['product:b', 'product:a', 'category:a'], $ids);
    }

    public function testEmptyUpdateIsDroppedButInsertAndDeleteAreKept(): void
    {
        $b = new CommitBuilder();
        $b->add($this->root('u'), ChangeAction::Update, []);
        $b->add($this->root('i'), ChangeAction::Insert, []);
        $b->add($this->root('d'), ChangeAction::Delete, []);

        $commits = $b->build();

        static::assertCount(2, $commits);
        static::assertSame(ChangeAction::Insert, $commits[0]->getAction());
        static::assertSame(ChangeAction::Delete, $commits[1]->getAction());
    }
}
