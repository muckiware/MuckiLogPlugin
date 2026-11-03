<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\SourceResolver;
use MuckiLogPlugin\ChangeLog\Struct\ChangeSourceType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Integration\IntegrationEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SourceResolverTest extends TestCase
{
    public function testUserSourceUsesFullName(): void
    {
        $id = Uuid::randomHex();
        $user = new UserEntity();
        $user->setUniqueIdentifier($id);
        $user->setFirstName('Max');
        $user->setLastName('Muster');
        $user->setUsername('mmuster');

        $source = $this->resolver($this->repo($user))->resolve(new Context(new AdminApiSource($id)));

        static::assertSame(ChangeSourceType::User, $source->type);
        static::assertSame($id, $source->userId);
        static::assertSame('Max Muster', $source->userName);
    }

    public function testUserNameFallsBackToUsername(): void
    {
        $id = Uuid::randomHex();
        $user = new UserEntity();
        $user->setUniqueIdentifier($id);
        $user->setFirstName('');
        $user->setLastName('');
        $user->setUsername('mmuster');

        $source = $this->resolver($this->repo($user))->resolve(new Context(new AdminApiSource($id)));

        static::assertSame('mmuster', $source->userName);
    }

    public function testIntegrationSourceUsesLabel(): void
    {
        $id = Uuid::randomHex();
        $integration = new IntegrationEntity();
        $integration->setUniqueIdentifier($id);
        $integration->setLabel('ERP Sync');

        $source = $this->resolver(null, $this->repo($integration))->resolve(new Context(new AdminApiSource(null, $id)));

        static::assertSame(ChangeSourceType::Integration, $source->type);
        static::assertSame($id, $source->integrationId);
        static::assertSame('ERP Sync', $source->sourceName);
    }

    public function testSalesChannelSourceUsesTranslatedName(): void
    {
        $id = Uuid::randomHex();
        $channel = new SalesChannelEntity();
        $channel->setUniqueIdentifier($id);
        $channel->setTranslated(['name' => 'Storefront']);

        $source = $this->resolver(null, null, $this->repo($channel))->resolve(new Context(new SalesChannelApiSource($id)));

        static::assertSame(ChangeSourceType::SalesChannel, $source->type);
        static::assertSame($id, $source->salesChannelId);
        static::assertSame('Storefront', $source->sourceName);
    }

    public function testAdminSalesChannelSourceFallsBackToOriginalAdmin(): void
    {
        $id = Uuid::randomHex();
        $user = new UserEntity();
        $user->setUniqueIdentifier($id);
        $user->setFirstName('Max');
        $user->setLastName('Muster');

        $original = new Context(new AdminApiSource($id));
        $context = new Context(new AdminSalesChannelApiSource(Uuid::randomHex(), $original));

        $source = $this->resolver($this->repo($user))->resolve($context);

        static::assertSame(ChangeSourceType::User, $source->type);
        static::assertSame('Max Muster', $source->userName);
    }

    public function testSystemSourceWithoutRequestHasNoIp(): void
    {
        $source = $this->resolver()->resolve(new Context(new SystemSource()));

        static::assertSame(ChangeSourceType::System, $source->type);
        static::assertNull($source->ipAddress);
    }

    public function testIpAddressComesFromRequest(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.20']));

        $source = $this->resolver(null, null, null, $stack)->resolve(new Context(new SystemSource()));

        static::assertSame('192.168.1.20', $source->ipAddress);
    }

    public function testNameIsLoadedOnlyOnce(): void
    {
        $id = Uuid::randomHex();
        $user = new UserEntity();
        $user->setUniqueIdentifier($id);
        $user->setFirstName('Max');
        $user->setLastName('Muster');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(static::once())->method('search')->willReturn($this->searchResult($user));
        $resolver = $this->resolver($repo);
        $context = new Context(new AdminApiSource($id));

        $resolver->resolve($context);
        $second = $resolver->resolve($context);

        static::assertSame('Max Muster', $second->userName);
    }

    public function testResetClearsNameCache(): void
    {
        $id = Uuid::randomHex();
        $user = new UserEntity();
        $user->setUniqueIdentifier($id);
        $user->setFirstName('Max');
        $user->setLastName('Muster');

        $repo = $this->createMock(EntityRepository::class);
        $repo->expects(static::exactly(2))->method('search')->willReturn($this->searchResult($user));
        $resolver = $this->resolver($repo);
        $context = new Context(new AdminApiSource($id));

        $resolver->resolve($context);
        $resolver->reset();
        $resolver->resolve($context);
    }

    private function resolver(
        ?EntityRepository $user = null,
        ?EntityRepository $integration = null,
        ?EntityRepository $channel = null,
        ?RequestStack $stack = null,
    ): SourceResolver {
        return new SourceResolver(
            $user ?? $this->createMock(EntityRepository::class),
            $integration ?? $this->createMock(EntityRepository::class),
            $channel ?? $this->createMock(EntityRepository::class),
            $stack ?? new RequestStack()
        );
    }

    private function repo(Entity $entity): EntityRepository&MockObject
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('search')->willReturn($this->searchResult($entity));

        return $repo;
    }

    private function searchResult(Entity $entity): EntitySearchResult
    {
        return new EntitySearchResult($entity->getApiAlias(), 1, new EntityCollection([$entity]), null, new Criteria(), Context::createDefaultContext());
    }
}
