<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\EntityGraph;
use MuckiLogPlugin\ChangeLog\RootResolver;
use MuckiLogPlugin\ChangeLog\Struct\RootReference;
use MuckiLogPlugin\tests\TestCaseBase\RequiresEntityDefinitions;
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
    use RequiresEntityDefinitions;
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
        $this->skipIfEntitiesMissing('product_translation');

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
        $this->skipIfEntitiesMissing('product_price');

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
        $this->skipIfEntitiesMissing('product_category');

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
        $this->skipIfEntitiesMissing('order', 'salutation');

        $order = $this->createOrder();

        static::assertEquals(
            new RootReference('order', $order['orderId'], 'order_delivery_position', ['id' => $order['positionId']]),
            $this->resolver->resolve($this->positionResult($order['positionId'], $order['deliveryId']), self::TRACKED)
        );
    }

    public function testFailedLookupIsNotCached(): void
    {
        $this->skipIfEntitiesMissing('order', 'salutation');

        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $data = $this->getOrderData($orderId, $context);
        $deliveryId = $data[0]['deliveries'][0]['id'];
        $positionId = Uuid::randomHex();

        static::assertNull($this->resolver->resolve($this->positionResult($positionId, $deliveryId), self::TRACKED));

        static::getContainer()->get('order.repository')->create($data, $context);

        static::assertEquals(
            new RootReference('order', $orderId, 'order_delivery_position', ['id' => $positionId]),
            $this->resolver->resolve($this->positionResult($positionId, $deliveryId), self::TRACKED)
        );
    }

    public function testResetClearsLookupCache(): void
    {
        $this->skipIfEntitiesMissing('order', 'salutation');

        $first = $this->createOrder();
        $second = $this->createOrder();
        $result = $this->positionResult($first['positionId'], $first['deliveryId']);
        static::assertSame($first['orderId'], $this->resolver->resolve($result, self::TRACKED)?->entityId);

        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE order_delivery SET order_id = UNHEX(:orderId) WHERE id = UNHEX(:deliveryId)',
            ['orderId' => $second['orderId'], 'deliveryId' => $first['deliveryId']]
        );

        static::assertSame($first['orderId'], $this->resolver->resolve($result, self::TRACKED)?->entityId);
        $this->resolver->reset();
        static::assertSame($second['orderId'], $this->resolver->resolve($result, self::TRACKED)?->entityId);
    }

    public function testOneToOneChildResolvesToParent(): void
    {
        $this->skipIfEntitiesMissing('order_customer');

        $orderId = Uuid::randomHex();
        $orderCustomerId = Uuid::randomHex();
        $result = new EntityWriteResult(
            $orderCustomerId,
            ['id' => $orderCustomerId, 'orderId' => $orderId, 'email' => 'a@b.c'],
            'order_customer',
            EntityWriteResult::OPERATION_UPDATE
        );

        static::assertEquals(
            new RootReference('order', $orderId, 'order_customer', ['id' => $orderCustomerId]),
            $this->resolver->resolve($result, ['order'])
        );
    }

    public function testTrackedSubEntityResolvesToTopmostTrackedRoot(): void
    {
        $this->skipIfEntitiesMissing('customer_address');

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

    /**
     * @return array{orderId: string, deliveryId: string, positionId: string}
     */
    private function createOrder(): array
    {
        $orderId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $data = $this->getOrderData($orderId, $context);
        $data[0]['deepLinkCode'] = Uuid::randomHex();
        static::getContainer()->get('order.repository')->create($data, $context);
        $row = static::getContainer()->get(Connection::class)->fetchAssociative(
            'SELECT LOWER(HEX(p.id)) AS id, LOWER(HEX(p.order_delivery_id)) AS delivery
             FROM order_delivery_position p JOIN order_delivery d ON d.id = p.order_delivery_id
             WHERE d.order_id = UNHEX(:orderId)',
            ['orderId' => $orderId]
        );
        static::assertIsArray($row);

        return ['orderId' => $orderId, 'deliveryId' => $row['delivery'], 'positionId' => $row['id']];
    }

    private function positionResult(string $positionId, string $deliveryId): EntityWriteResult
    {
        return new EntityWriteResult(
            $positionId,
            ['id' => $positionId, 'quantity' => 2],
            'order_delivery_position',
            EntityWriteResult::OPERATION_UPDATE,
            null,
            new ChangeSet(['order_delivery_id' => Uuid::fromHexToBytes($deliveryId), 'quantity' => 1], ['quantity' => 2], false)
        );
    }
}
