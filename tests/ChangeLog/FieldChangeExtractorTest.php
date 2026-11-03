<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\FieldChangeExtractor;
use MuckiLogPlugin\ChangeLog\SensitiveFieldMasker;
use MuckiLogPlugin\ChangeLog\ValueNormalizer;
use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class FieldChangeExtractorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private FieldChangeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new FieldChangeExtractor(
            static::getContainer()->get(DefinitionInstanceRegistry::class),
            new ValueNormalizer(),
            new SensitiveFieldMasker()
        );
    }

    public function testInsertUsesPayloadAsNew(): void
    {
        $result = new EntityWriteResult('id1', [
            'id' => 'id1', 'versionId' => 'v', 'createdAt' => 'x', 'updatedAt' => 'y',
            'stock' => 5, 'productNumber' => 'X',
        ], 'product', EntityWriteResult::OPERATION_INSERT);

        $changes = $this->extractor->extract($result, null, []);

        static::assertSame(['stock', 'productNumber'], array_map(static fn ($c) => $c->field, $changes));
        static::assertNull($changes[0]->old);
        static::assertSame(5, $changes[0]->new);
        static::assertSame('X', $changes[1]->new);
    }

    public function testUpdateUsesChangeSetAndSkipsUnchanged(): void
    {
        $result = new EntityWriteResult(
            'id1',
            ['stock' => 7, 'active' => true],
            'product',
            EntityWriteResult::OPERATION_UPDATE,
            null,
            new ChangeSet(['stock' => 5, 'active' => 1, 'product_number' => 'A'], ['stock' => 7, 'active' => true, 'product_number' => 'B'], false)
        );

        $changes = $this->extractor->extract($result, null, []);

        static::assertSame(['stock', 'productNumber'], array_map(static fn ($c) => $c->field, $changes));
        static::assertSame(5, $changes[0]->old);
        static::assertSame(7, $changes[0]->new);
        static::assertSame('A', $changes[1]->old);
        static::assertSame('B', $changes[1]->new);
    }

    public function testUpdateWithoutChangeSetFallsBackToPayload(): void
    {
        $result = new EntityWriteResult('id1', ['stock' => 7], 'product', EntityWriteResult::OPERATION_UPDATE);

        $changes = $this->extractor->extract($result, null, []);

        static::assertCount(1, $changes);
        static::assertNull($changes[0]->old);
        static::assertSame(7, $changes[0]->new);
    }

    public function testDeleteUsesChangeSetBefore(): void
    {
        $result = new EntityWriteResult(
            'id1',
            [],
            'product',
            EntityWriteResult::OPERATION_DELETE,
            null,
            new ChangeSet(['id' => 'x', 'stock' => 5, 'product_number' => 'A'], [], true)
        );

        $changes = $this->extractor->extract($result, null, []);

        static::assertSame(['stock', 'productNumber'], array_map(static fn ($c) => $c->field, $changes));
        foreach ($changes as $change) {
            static::assertNull($change->new);
        }
        static::assertSame(5, $changes[0]->old);
    }

    public function testDeleteSkipsUnknownAssociationAndIndexerColumns(): void
    {
        $result = new EntityWriteResult(
            'id1',
            [],
            'product',
            EntityWriteResult::OPERATION_DELETE,
            null,
            new ChangeSet([
                'name' => 'Shirt',
                'stock' => 5,
                'auto_increment' => 12,
                'cheapest_price' => 'O:8:"stdClass":0:{}',
                'cheapest_price_accessor' => '{}',
                'display_group' => 'abc',
                'states' => '["is-physical"]',
                'child_count' => 0,
                'media' => 'id1',
                'translations' => 'id1',
                'some_unknown_column' => 'x',
            ], [], true)
        );

        $fields = array_map(static fn ($c) => $c->field, $this->extractor->extract($result, null, []));

        static::assertSame(['name', 'stock'], $fields);
    }

    public function testSensitiveFieldIsMasked(): void
    {
        $result = new EntityWriteResult(
            'id1',
            ['password' => 'new'],
            'user',
            EntityWriteResult::OPERATION_UPDATE,
            null,
            new ChangeSet(['password' => 'oldhash'], ['password' => 'newhash'], false)
        );

        $changes = $this->extractor->extract($result, null, []);

        static::assertCount(1, $changes);
        static::assertSame(SensitiveFieldMasker::MASK, $changes[0]->old);
        static::assertSame(SensitiveFieldMasker::MASK, $changes[0]->new);
        static::assertTrue($changes[0]->masked);
    }

    public function testNestedSecretsAreMasked(): void
    {
        $result = new EntityWriteResult(
            'id1',
            [],
            'product_translation',
            EntityWriteResult::OPERATION_UPDATE,
            null,
            new ChangeSet(['custom_fields' => '{"color":"blue"}'], ['custom_fields' => '{"apiKey":"x","color":"red"}'], false)
        );

        $changes = $this->extractor->extract($result, null, []);

        static::assertCount(1, $changes);
        static::assertSame(['color' => 'blue'], $changes[0]->old);
        static::assertSame(['apiKey' => '***', 'color' => 'red'], $changes[0]->new);
        static::assertTrue($changes[0]->masked);
    }

    public function testSubEntityAndContextArePassedThrough(): void
    {
        $result = new EntityWriteResult('id1', ['name' => 'Foo', 'productId' => 'p', 'languageId' => 'l'], 'product_translation', EntityWriteResult::OPERATION_INSERT);

        $changes = $this->extractor->extract($result, 'product_translation', ['languageId' => 'l']);

        static::assertCount(1, $changes);
        static::assertSame('name', $changes[0]->field);
        static::assertSame('product_translation', $changes[0]->subEntity);
        static::assertSame(['languageId' => 'l'], $changes[0]->context);
    }

    public function testRootRowKeepsForeignKeys(): void
    {
        $result = new EntityWriteResult('id1', ['taxId' => 't'], 'product', EntityWriteResult::OPERATION_INSERT);

        $changes = $this->extractor->extract($result, null, []);

        static::assertSame(['taxId'], array_map(static fn ($c) => $c->field, $changes));
    }

    public function testChangeActionMapping(): void
    {
        static::assertSame(ChangeAction::Insert, ChangeAction::fromOperation('insert'));
        static::assertSame(ChangeAction::Update, ChangeAction::fromOperation('update'));
        static::assertSame(ChangeAction::Delete, ChangeAction::fromOperation('delete'));
    }
}
