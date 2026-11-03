<?php declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogDefinition;
use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogEntity;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class AdminChangeLogDefinitionTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testCreateAndReadWithUserAssociation(): void
    {
        $userId = $this->createUser();
        $id = $this->createEntry(['userId' => $userId]);

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('user');
        $entry = $this->repo()->search($criteria, $this->context)->first();

        static::assertInstanceOf(AdminChangeLogEntity::class, $entry);
        static::assertSame($userId, $entry->getUser()?->getId());
        static::assertSame('b', $entry->getChanges()[0]['new']);
    }

    public function testUserDeletionKeepsEntryAndName(): void
    {
        $userId = $this->createUser();
        $id = $this->createEntry(['userId' => $userId, 'userName' => 'Max Admin']);

        /** @var EntityRepository $userRepo */
        $userRepo = $this->getContainer()->get('user.repository');
        $userRepo->delete([['id' => $userId]], $this->context);

        $entry = $this->repo()->search(new Criteria([$id]), $this->context)->first();

        static::assertInstanceOf(AdminChangeLogEntity::class, $entry);
        static::assertNull($entry->getUserId());
        static::assertSame('Max Admin', $entry->getUserName());
    }

    public function testTermSearchFindsByLabelAndUserName(): void
    {
        $id = $this->createEntry(['entityLabel' => 'Rotes T-Shirt']);

        $criteria = new Criteria();
        $criteria->setTerm('Rotes');
        $result = $this->repo()->searchIds($criteria, $this->context);

        static::assertContains($id, $result->getIds());
    }

    private function repo(): EntityRepository
    {
        return $this->getContainer()->get(AdminChangeLogDefinition::ENTITY_NAME . '.repository');
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEntry(array $overrides = []): string
    {
        $id = Uuid::randomHex();
        $this->repo()->create([array_merge([
            'id' => $id,
            'entityName' => 'product',
            'entityId' => Uuid::randomHex(),
            'entityLabel' => 'Label',
            'action' => 'update',
            'sourceType' => 'user',
            'userName' => 'Max Admin',
            'changes' => [['field' => 'name', 'old' => 'a', 'new' => 'b']],
        ], $overrides)], $this->context);

        return $id;
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        $localeId = $this->getContainer()->get('language.repository')
            ->search((new Criteria([Defaults::LANGUAGE_SYSTEM])), $this->context)
            ->first()->getLocaleId();

        $this->getContainer()->get('user.repository')->create([[
            'id' => $id,
            'localeId' => $localeId,
            'username' => 'maxadmin_' . $id,
            'password' => 'Test-' . Uuid::randomHex(),
            'email' => $id . '@example.com',
            'firstName' => 'Max',
            'lastName' => 'Admin',
            'admin' => true,
        ]], $this->context);

        return $id;
    }
}
