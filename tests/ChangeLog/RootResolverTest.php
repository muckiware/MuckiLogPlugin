<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\EntityGraph;
use MuckiLogPlugin\ChangeLog\RootResolver;
use MuckiLogPlugin\ChangeLog\Struct\RootReference;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Traits\OrderFixture;

class RootResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use OrderFixture;

    private const TRACKED = ['product', 'order', 'customer', 'customer_address', 'category'];

    private RootResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RootResolver(
            new EntityGraph(static::getContainer()->get(DefinitionInstanceRegistry::class)),
            static::getContainer()->get(Connection::class)
        );
    }

    public function testTranslationResolvesToParentWithLanguage(): void
    {
        $productId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $result = new EntityWriteResult(
            ['productId' => $productId, 'languageId' => $languageId, 'productVersionId' => Uuid::randomHex()],
            ['productId' => $productId, 'languageId' => $languageId, 'name' => 'X'],
            'product_translation',
            EntityWriteResult::OPERATION_UPDATE
        );

        static::assertEquals(
            new RootReference('product', $productId, 'product_translation', ['languageId' => $languageId]),
            $this->resolver->resolve($result, self::TRACKED)
        );
    }

    public function testChildResolvesToParentWithChildId(): void
    {
        $productId = Uuid::randomHex();
        $priceId = Uuid::randomHex();
        $result = new EntityWriteResult($priceId, ['id' => $priceId, 'productId' => $productId], 'product_price', EntityWriteResult::OPERATION_INSERT);

        static::assertEquals(
            new RootReference('product', $productId, 'product_price', ['id' => $priceId]),
            $this->resolver->resolve($result, self::TRACKED)
        );
    }

    public function testMappingResolvesToFirstSideWithLinkedEntity(): void
    {
        $productId = Uuid::randomHex();
        $categoryId = Uuid::randomHex();
        $result = new EntityWriteResult(
            ['productId' => $productId, 'categoryId' => $categoryId],
            ['productId' => $productId, 'categoryId' => $categoryId],
            'product_category',
            EntityWriteResult::OPERATION_INSERT
        );

        static::assertEquals(
            new RootReference('product', $productId, 'product_category', ['linkedEntity' => 'category', 'linkedId' => $categoryId]),
            $this->resolver->resolve($result, self::TRACKED)
        );
    }

    public function testMultiLevelChildResolvesToOrderViaDatabase(): void
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        static::getContainer()->get('order.repository')->create($this->getOrderData($orderId, $context), $context);
        $row = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT LOWER(HEX(p.id)) AS id, LOWER(HEX(p.order_delivery_id)) AS delivery
             FROM order_delivery_position p JOIN order_delivery d ON d.id = p.order_delivery_id
             WHERE d.order_id = UNHEX(:orderId)',
            ['orderId' => $orderId]
        );
        static::assertIsArray($row);

        $result = new EntityWriteResult(
            $row['id'],
            ['id' => $row['id'], 'quantity' => 2],
            'order_delivery_position',
            EntityWriteResult::OPERATION_UPDATE,
            null,
            new ChangeSet(['order_delivery_id' => Uuid::fromHexToBytes($row['delivery']), 'quantity' => 1], ['quantity' => 2], false)
        );

        static::assertEquals(
            new RootReference('order', $orderId, 'order_delivery_position', ['id' => $row['id']]),
            $this->resolver->resolve($result, self::TRACKED)
        );
    }

    public function testTrackedSubEntityResolvesToTopmostTrackedRoot(): void
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();
        $result = new EntityWriteResult(
            $addressId,
            ['id' => $addressId, 'city' => 'B'],
            'customer_address',
            EntityWriteResult::OPERATION_UPDATE,
            null,
            new ChangeSet(['customer_id' => Uuid::fromHexToBytes($customerId), 'city' => 'A'], ['city' => 'B'], false)
        );

        static::assertEquals(
            new RootReference('customer', $customerId, 'customer_address', ['id' => $addressId]),
            $this->resolver->resolve($result, self::TRACKED)
        );
    }

    public function testVariantIsItsOwnRoot(): void
    {
        $variantId = Uuid::randomHex();
        $result = new EntityWriteResult(
            $variantId,
            ['id' => $variantId, 'parentId' => Uuid::randomHex(), 'stock' => 3],
            'product',
            EntityWriteResult::OPERATION_UPDATE
        );

        static::assertEquals(
            new RootReference('product', $variantId, null, []),
            $this->resolver->resolve($result, self::TRACKED)
        );
    }

    public function testSearchKeywordIsDropped(): void
    {
        $result = new EntityWriteResult(Uuid::randomHex(), ['productId' => Uuid::randomHex()], 'product_search_keyword', EntityWriteResult::OPERATION_INSERT);

        static::assertNull($this->resolver->resolve($result, self::TRACKED));
    }

    public function testUntrackedEntityIsDropped(): void
    {
        $result = new EntityWriteResult(Uuid::randomHex(), ['taxRate' => 7.0], 'tax', EntityWriteResult::OPERATION_UPDATE);

        static::assertNull($this->resolver->resolve($result, self::TRACKED));
    }

    public function testUnresolvableForeignKeyIsDropped(): void
    {
        $priceId = Uuid::randomHex();
        $result = new EntityWriteResult($priceId, ['id' => $priceId, 'quantityStart' => 2], 'product_price', EntityWriteResult::OPERATION_UPDATE);

        static::assertNull($this->resolver->resolve($result, self::TRACKED));
    }
}
