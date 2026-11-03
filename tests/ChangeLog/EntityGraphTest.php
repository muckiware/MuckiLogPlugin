<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\EntityGraph;
use MuckiLogPlugin\ChangeLog\Struct\ParentLink;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class EntityGraphTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TRACKED = ['product', 'order', 'customer', 'customer_address', 'category'];

    private EntityGraph $graph;

    protected function setUp(): void
    {
        $this->graph = new EntityGraph(static::getContainer()->get(DefinitionInstanceRegistry::class));
    }

    public function testTranslationLinksToParent(): void
    {
        static::assertEquals(
            [new ParentLink('product', 'product_id', 'productId', 'translation')],
            $this->graph->getParentLinks('product_translation')
        );
    }

    public function testCascadeDeleteChildLinksToParent(): void
    {
        static::assertContainsEquals(
            new ParentLink('product', 'product_id', 'productId', 'child'),
            $this->graph->getParentLinks('product_price')
        );
    }

    public function testOneToOneOwnedChildLinksToParent(): void
    {
        static::assertContainsEquals(
            new ParentLink('order', 'order_id', 'orderId', 'child'),
            $this->graph->getParentLinks('order_customer')
        );
        static::assertTrue($this->graph->isRelevant('order_customer', ['order']));
    }

    public function testOneToOneOwningSideIsNoChild(): void
    {
        $parents = array_map(static fn (ParentLink $l) => $l->parentEntity, $this->graph->getParentLinks('sales_channel_analytics'));

        static::assertNotContains('sales_channel', $parents);
    }

    public function testMappingLinksBothSidesInDefinitionOrder(): void
    {
        $links = $this->graph->getParentLinks('product_category');

        static::assertEquals([
            new ParentLink('product', 'product_id', 'productId', 'mapping'),
            new ParentLink('category', 'category_id', 'categoryId', 'mapping'),
        ], $links);
    }

    public function testVariantsAndSelfReferencesAreNoChildren(): void
    {
        $parents = array_map(static fn (ParentLink $l) => $l->parentEntity, $this->graph->getParentLinks('product'));

        static::assertNotContains('product', $parents);
    }

    public function testSearchKeywordIsNotRelevant(): void
    {
        static::assertSame([], array_filter(
            $this->graph->getParentLinks('product_search_keyword'),
            static fn (ParentLink $l) => $l->parentEntity === 'product'
        ));
        static::assertFalse($this->graph->isRelevant('product_search_keyword', self::TRACKED));
    }

    public function testRelevance(): void
    {
        static::assertTrue($this->graph->isRelevant('product', self::TRACKED));
        static::assertTrue($this->graph->isRelevant('product_translation', self::TRACKED));
        static::assertTrue($this->graph->isRelevant('order_delivery_position', self::TRACKED));
        static::assertFalse($this->graph->isRelevant('tax', self::TRACKED));
        static::assertFalse($this->graph->isRelevant('order_delivery_position', ['product']));
    }

    public function testSystemConfigAndOwnLogAreNeverRelevant(): void
    {
        static::assertFalse($this->graph->isRelevant('system_config', ['system_config']));
        static::assertFalse($this->graph->isRelevant('muwa_admin_change_log', ['muwa_admin_change_log']));
    }

    public function testUnknownEntityHasNoLinks(): void
    {
        static::assertSame([], $this->graph->getParentLinks('does_not_exist'));
        static::assertFalse($this->graph->isRelevant('does_not_exist', self::TRACKED));
    }
}
