<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use MuckiLogPlugin\ChangeLog\ChangeLogWriter;
use MuckiLogPlugin\ChangeLog\EntityGraph;
use MuckiLogPlugin\ChangeLog\EntityLabelResolver;
use MuckiLogPlugin\ChangeLog\FieldChangeExtractor;
use MuckiLogPlugin\ChangeLog\RootResolver;
use MuckiLogPlugin\ChangeLog\SourceResolver;
use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogEntity;
use MuckiLogPlugin\Core\ConfigPath;
use MuckiLogPlugin\Subscriber\ChangeLogWriteSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChangeLogWriteSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private EntityRepository $productRepository;

    private EntityRepository $changeLogRepository;

    private SystemConfigService $systemConfig;

    private Context $context;

    private string $userId;

    private string $productId;

    private ?ChangeLogWriteSubscriber $extraSubscriber = null;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->productRepository = $container->get('product.repository');
        $this->changeLogRepository = $container->get('muwa_admin_change_log.repository');
        $this->systemConfig = $container->get(SystemConfigService::class);

        $this->userId = $this->createUser();
        $source = new AdminApiSource($this->userId);
        $source->setIsAdmin(true);
        $this->context = new Context($source);

        $this->productId = Uuid::randomHex();
        $this->productRepository->create([$this->productPayload($this->productId, 'Old name')], Context::createDefaultContext());

        $this->systemConfig->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, true);
        $this->setTracked(['product']);
    }

    protected function tearDown(): void
    {
        if ($this->extraSubscriber !== null) {
            static::getContainer()->get('event_dispatcher')->removeSubscriber($this->extraSubscriber);
            $this->extraSubscriber = null;
        }
    }

    public function testProductNameEnAndPriceGiveOneCommit(): void
    {
        $this->productRepository->update([[
            'id' => $this->productId,
            'translations' => ['en-GB' => ['name' => 'New name']],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 16.8, 'linked' => false]],
        ]], $this->context);

        $entries = $this->entriesFor($this->productId);
        static::assertCount(1, $entries);
        $entry = $entries[0];
        static::assertSame('product', $entry->get('entityName'));
        static::assertSame('update', $entry->get('action'));
        static::assertSame('user', $entry->get('sourceType'));
        static::assertSame($this->userId, $entry->get('userId'));

        $name = $this->changeNamed($entry, 'name');
        static::assertSame('product_translation', $name['subEntity']);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $name['context']['languageId']);
        static::assertSame('Old name', $name['old']);
        static::assertSame('New name', $name['new']);
        static::assertNotNull($this->changeNamed($entry, 'price'));
    }

    public function testIdenticalPriceGivesNoEntry(): void
    {
        $this->productRepository->update([[
            'id' => $this->productId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.4, 'linked' => false]],
        ]], $this->context);

        static::assertCount(0, $this->entriesFor($this->productId));
    }

    public function testIdenticalPriceIsNotListedNextToRealChange(): void
    {
        $this->productRepository->update([[
            'id' => $this->productId,
            'stock' => 11,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.4, 'linked' => false]],
        ]], $this->context);

        $entries = $this->entriesFor($this->productId);
        static::assertCount(1, $entries);
        static::assertSame(['stock'], array_column($entries[0]->get('changes'), 'field'));
    }

    public function testTranslationOnlyUpdateHasOldValue(): void
    {
        $this->productRepository->update([[
            'id' => $this->productId,
            'translations' => ['en-GB' => ['name' => 'Renamed']],
        ]], $this->context);

        $entries = $this->entriesFor($this->productId);
        static::assertCount(1, $entries);
        $name = $this->changeNamed($entries[0], 'name');
        static::assertSame('Old name', $name['old']);
        static::assertSame('Renamed', $name['new']);
        static::assertSame('Renamed', $entries[0]->get('entityLabel'));
    }

    public function testVariantWithoutOwnNameUsesParentNameAsLabel(): void
    {
        $variantId = Uuid::randomHex();
        $this->productRepository->create([[
            'id' => $variantId,
            'parentId' => $this->productId,
            'productNumber' => 'CL-V-' . $variantId,
            'stock' => 3,
        ]], Context::createDefaultContext());

        $this->productRepository->update([['id' => $variantId, 'stock' => 4]], $this->context);

        $updates = array_values(array_filter(
            $this->entriesFor($variantId),
            static fn (AdminChangeLogEntity $e): bool => $e->get('action') === 'update'
        ));
        static::assertCount(1, $updates);
        static::assertSame('Old name', $updates[0]->get('entityLabel'));
    }

    public function testCustomerLastLoginOnlyGivesNoEntry(): void
    {
        $this->setTracked(['customer']);
        $customerId = $this->createCustomer();

        static::getContainer()->get('customer.repository')->update([[
            'id' => $customerId,
            'lastLogin' => new \DateTimeImmutable(),
        ]], $this->context);

        static::assertCount(0, array_filter(
            $this->entriesFor($customerId),
            static fn (AdminChangeLogEntity $e): bool => $e->get('action') === 'update'
        ));
    }

    public function testBulkUpsert50Products(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects(static::once())
            ->method('create')
            ->with(static::callback(static fn (array $payload): bool => \count($payload) === 50));
        $this->addExtraSubscriber($repository, $this->createMock(LoggerInterface::class));

        $payloads = [];
        for ($i = 0; $i < 50; ++$i) {
            $payloads[] = $this->productPayload(Uuid::randomHex(), 'Bulk ' . $i);
        }
        $this->productRepository->upsert($payloads, $this->context);

        $ids = array_column($payloads, 'id');
        $criteria = (new Criteria())->addFilter(new EqualsFilter('entityName', 'product'));
        $logged = $this->changeLogRepository->search($criteria, Context::createDefaultContext())->getEntities();
        $loggedIds = array_values(array_intersect($ids, array_map(
            static fn (AdminChangeLogEntity $e): string => (string) $e->get('entityId'),
            array_values($logged->getElements())
        )));
        static::assertCount(50, $loggedIds);
    }

    public function testDeleteLogsBeforeValues(): void
    {
        $this->productRepository->delete([['id' => $this->productId]], $this->context);

        $entries = $this->entriesFor($this->productId);
        static::assertCount(1, $entries);
        static::assertSame('delete', $entries[0]->get('action'));
        $name = $this->changeNamed($entries[0], 'name');
        static::assertSame('Old name', $name['old']);
        static::assertNull($name['new']);
        static::assertCount(1, $this->allEntries(), 'cascade-deleted children must not create own commits');
    }

    public function testCategoryAssignmentIsLogged(): void
    {
        $catId = Uuid::randomHex();
        $this->createCategory($catId);

        $this->productRepository->update([[
            'id' => $this->productId,
            'categories' => [['id' => $catId]],
        ]], $this->context);

        $entries = $this->entriesFor($this->productId);
        static::assertCount(1, $entries);
        $change = $this->changeNamed($entries[0], 'category');
        static::assertSame('product_category', $change['subEntity']);
        static::assertNull($change['old']);
        static::assertSame($catId, $change['new']);

        static::getContainer()->get('product_category.repository')->delete(
            [['productId' => $this->productId, 'categoryId' => $catId]],
            $this->context
        );

        $entries = $this->entriesFor($this->productId);
        static::assertCount(2, $entries);
        $removed = null;
        foreach ($entries as $entry) {
            foreach ($entry->get('changes') ?? [] as $c) {
                if (($c['field'] ?? null) === 'category' && ($c['new'] ?? null) === null) {
                    $removed = $c;
                }
            }
        }
        static::assertNotNull($removed);
        static::assertSame($catId, $removed['old']);
    }

    private function createCategory(string $id): void
    {
        static::getContainer()->get('category.repository')->create(
            [['id' => $id, 'name' => 'Cat ' . $id]],
            Context::createDefaultContext()
        );
    }

    public function testInactiveLogsNothing(): void
    {
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, false);

        $this->productRepository->update([['id' => $this->productId, 'stock' => 99]], $this->context);

        static::assertCount(0, $this->entriesFor($this->productId));
    }

    public function testUntrackedEntityLogsNothing(): void
    {
        $this->setTracked(['category']);

        $this->productRepository->update([['id' => $this->productId, 'stock' => 99]], $this->context);

        static::assertCount(0, $this->allEntries());
    }

    public function testDraftVersionWriteIsSkipped(): void
    {
        $versionId = $this->productRepository->createVersion($this->productId, $this->context);
        $draftContext = $this->context->createWithVersionId($versionId);

        $this->productRepository->update([['id' => $this->productId, 'stock' => 99]], $draftContext);

        static::assertCount(0, $this->entriesFor($this->productId));
    }

    public function testOwnEntityIsNotLoggedRecursively(): void
    {
        $this->setTracked(['product', 'muwa_admin_change_log']);

        $this->productRepository->update([['id' => $this->productId, 'stock' => 99]], $this->context);
        $this->changeLogRepository->create([[
            'entityName' => 'product',
            'entityId' => Uuid::randomHex(),
            'action' => 'update',
            'sourceType' => 'system',
        ]], $this->context);

        $all = $this->allEntries();
        static::assertCount(2, $all);
        foreach ($all as $entry) {
            static::assertNotSame('muwa_admin_change_log', $entry->get('entityName'));
        }
    }

    public function testWriterFailureDoesNotBreakWrite(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('create')->willThrowException(new \RuntimeException('db down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::atLeastOnce())
            ->method('error')
            ->with(static::anything(), ['muwa', 'admin-change-log']);
        $this->addExtraSubscriber($repository, $logger);

        $this->productRepository->update([['id' => $this->productId, 'stock' => 77]], $this->context);

        $product = $this->productRepository->search(new Criteria([$this->productId]), Context::createDefaultContext())->first();
        static::assertNotNull($product);
        static::assertSame(77, $product->get('stock'));
    }

    public function testUserPasswordIsMasked(): void
    {
        $this->setTracked(['user']);

        static::getContainer()->get('user.repository')->update([[
            'id' => $this->userId,
            'password' => 'a-new-secret-password',
        ]], $this->context);

        $entries = $this->entriesFor($this->userId);
        static::assertCount(1, $entries);
        $password = $this->changeNamed($entries[0], 'password');
        static::assertSame('***', $password['old']);
        static::assertSame('***', $password['new']);
        static::assertTrue($password['masked']);
    }

    /**
     * @param list<string> $entities
     */
    private function setTracked(array $entities): void
    {
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_CHANGE_LOG_ENTITIES->value, $entities);
    }

    private function addExtraSubscriber(EntityRepository $repository, LoggerInterface $logger): void
    {
        $container = static::getContainer();
        $this->extraSubscriber = new ChangeLogWriteSubscriber(
            $container->get(ChangeLogConfig::class),
            $container->get(EntityGraph::class),
            $container->get(RootResolver::class),
            $container->get(FieldChangeExtractor::class),
            $container->get(SourceResolver::class),
            new ChangeLogWriter($repository, $container->get(EntityLabelResolver::class), $logger),
            $logger,
        );
        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = $container->get('event_dispatcher');
        $dispatcher->addSubscriber($this->extraSubscriber);
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        $localeId = static::getContainer()->get(Connection::class)
            ->fetchOne("SELECT LOWER(HEX(id)) FROM locale WHERE code = 'en-GB'");
        static::getContainer()->get('user.repository')->create([[
            'id' => $id,
            'localeId' => $localeId,
            'username' => 'changelog-' . $id,
            'password' => 'initial-password-123',
            'firstName' => 'Change',
            'lastName' => 'Logger',
            'email' => $id . '@example.com',
        ]], Context::createDefaultContext());

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $id, string $name): array
    {
        return [
            'id' => $id,
            'productNumber' => 'CL-' . $id,
            'stock' => 10,
            'name' => $name,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8.4, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 19],
        ];
    }

    /**
     * @return list<AdminChangeLogEntity>
     */
    private function entriesFor(string $entityId): array
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('entityId', $entityId));

        return array_values($this->changeLogRepository->search($criteria, Context::createDefaultContext())->getEntities()->getElements());
    }

    /**
     * @return list<AdminChangeLogEntity>
     */
    private function allEntries(): array
    {
        return array_values($this->changeLogRepository->search(new Criteria(), Context::createDefaultContext())->getEntities()->getElements());
    }

    /**
     * @return array<string, mixed>
     */
    private function changeNamed(AdminChangeLogEntity $entry, string $field): array
    {
        foreach ($entry->get('changes') ?? [] as $change) {
            if (($change['field'] ?? null) === $field) {
                return $change;
            }
        }
        static::fail(\sprintf('No change for field "%s" in %s', $field, json_encode($entry->get('changes'))));
    }
}
