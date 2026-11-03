<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\EntityLabelResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityLabelResolverTest extends TestCase
{
    public function testProductUsesTranslatedName(): void
    {
        $product = new ProductEntity();
        $product->setUniqueIdentifier($id = Uuid::randomHex());
        $product->setTranslated(['name' => 'Shirt']);

        static::assertSame([$id => 'Shirt'], $this->resolve('product', [$id => []], [$product]));
    }

    public function testOrderUsesOrderNumber(): void
    {
        $order = new OrderEntity();
        $order->setUniqueIdentifier($id = Uuid::randomHex());
        $order->setOrderNumber('10001');

        static::assertSame([$id => '10001'], $this->resolve('order', [$id => []], [$order]));
    }

    public function testCustomerUsesNameAndNumber(): void
    {
        $customer = new CustomerEntity();
        $customer->setUniqueIdentifier($id = Uuid::randomHex());
        $customer->setFirstName('Erika');
        $customer->setLastName('Mustermann');
        $customer->setCustomerNumber('K-1');

        static::assertSame([$id => 'Erika Mustermann (K-1)'], $this->resolve('customer', [$id => []], [$customer]));
    }

    public function testCmsPageUsesTranslatedName(): void
    {
        $page = new CmsPageEntity();
        $page->setUniqueIdentifier($id = Uuid::randomHex());
        $page->setTranslated(['name' => 'Landing']);

        static::assertSame([$id => 'Landing'], $this->resolve('cms_page', [$id => []], [$page]));
    }

    public function testDeletedRecordUsesBeforeValues(): void
    {
        $id = Uuid::randomHex();

        static::assertSame([$id => 'Old name'], $this->resolve('product', [$id => ['name' => 'Old name']], []));
    }

    public function testDeletedOrderUsesStorageName(): void
    {
        $id = Uuid::randomHex();

        static::assertSame([$id => '10002'], $this->resolve('order', [$id => ['order_number' => '10002']], []));
    }

    public function testFallsBackToIdWhenNothingKnown(): void
    {
        $id = Uuid::randomHex();

        static::assertSame([$id => $id], $this->resolve('product', [$id => ['foo' => 'bar']], []));
    }

    public function testUnknownEntityDoesNotThrow(): void
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willThrowException(new \RuntimeException('unknown'));
        $id = Uuid::randomHex();

        $labels = (new EntityLabelResolver($registry, $this->createMock(Connection::class)))
            ->resolveMany('nope', [$id => ['title' => 'T']], Context::createDefaultContext());

        static::assertSame([$id => 'T'], $labels);
    }

    public function testResolveManyUsesSingleQuery(): void
    {
        $a = new OrderEntity();
        $a->setUniqueIdentifier($ida = Uuid::randomHex());
        $a->setOrderNumber('1');
        $b = new OrderEntity();
        $b->setUniqueIdentifier($idb = Uuid::randomHex());
        $b->setOrderNumber('2');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(static::once())->method('search')->willReturn($this->searchResult([$a, $b]));
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repo);

        $labels = (new EntityLabelResolver($registry, $this->createMock(Connection::class)))
            ->resolveMany('order', [$ida => [], $idb => []], Context::createDefaultContext());

        static::assertSame([$ida => '1', $idb => '2'], $labels);
    }

    /**
     * @param array<string, array<string, mixed>> $before
     * @param list<Entity> $entities
     *
     * @return array<string, string>
     */
    private function resolve(string $entity, array $before, array $entities): array
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->searchResult($entities));
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturn($repo);

        return (new EntityLabelResolver($registry, $this->createMock(Connection::class)))
            ->resolveMany($entity, $before, Context::createDefaultContext());
    }

    /**
     * @param list<Entity> $entities
     */
    private function searchResult(array $entities): EntitySearchResult
    {
        return new EntitySearchResult($entities === [] ? 'x' : $entities[0]->getApiAlias(), \count($entities), new EntityCollection($entities), null, new Criteria(), Context::createDefaultContext());
    }
}
