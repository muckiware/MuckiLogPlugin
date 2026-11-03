<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogEntity;
use MuckiLogPlugin\Core\ConfigPath;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class SystemConfigChangeSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private const DOMAIN = 'MuckiLogPlugin.config';

    private SystemConfigService $systemConfig;

    private EntityRepository $changeLogRepository;

    protected function setUp(): void
    {
        $container = static::getContainer();
        $this->systemConfig = $container->get(SystemConfigService::class);
        $this->changeLogRepository = $container->get('muwa_admin_change_log.repository');

        $this->systemConfig->set(self::DOMAIN . '.level', 'info');
        $this->systemConfig->set(self::DOMAIN . '.maxfilesize', '6');
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, true);
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_CHANGE_LOG_ENTITIES->value, ['system_config']);
        $this->clearEntries();
    }

    private function clearEntries(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM muwa_admin_change_log');
    }

    public function testOnlyChangedKeyIsLoggedForGlobalConfig(): void
    {
        $this->systemConfig->setMultiple([self::DOMAIN . '.level' => 'debug', self::DOMAIN . '.maxfilesize' => '6']);

        $entries = $this->entriesFor(self::DOMAIN . '|global');
        static::assertCount(1, $entries);
        static::assertSame('system_config', $entries[0]->get('entityName'));
        static::assertSame('Einstellungen MuckiLogPlugin.config (Alle Verkaufskanäle)', $entries[0]->get('entityLabel'));
        $changes = $entries[0]->get('changes');
        static::assertCount(1, $changes);
        static::assertSame(self::DOMAIN . '.level', $changes[0]['field']);
        static::assertSame('info', $changes[0]['old']);
        static::assertSame('debug', $changes[0]['new']);
    }

    public function testTwoDomainsGiveTwoEntries(): void
    {
        $this->systemConfig->setMultiple(['MuckiA.config.foo' => 'x', 'MuckiB.config.bar' => 'y']);

        static::assertCount(1, $this->entriesFor('MuckiA.config|global'));
        static::assertCount(1, $this->entriesFor('MuckiB.config|global'));
        static::assertCount(2, $this->allEntries());
    }

    public function testSalesChannelIdAndNameAreUsed(): void
    {
        $channel = $this->createSalesChannel(['name' => 'Mucki Testkanal']);
        $this->systemConfig->set(self::DOMAIN . '.level', 'warning', $channel['id']);

        $entries = $this->entriesFor(self::DOMAIN . '|' . $channel['id']);
        static::assertCount(1, $entries);
        static::assertSame('Einstellungen MuckiLogPlugin.config (Mucki Testkanal)', $entries[0]->get('entityLabel'));
        static::assertNull($entries[0]->get('changes')[0]['old']);
        static::assertSame(['salesChannelId' => $channel['id']], $entries[0]->get('changes')[0]['context']);
    }

    public function testSecretKeyIsMasked(): void
    {
        $this->systemConfig->set('SwagPayPal.settings.clientSecret', 'super-secret-value');

        $entries = $this->entriesFor('SwagPayPal.settings|global');
        static::assertCount(1, $entries);
        $change = $entries[0]->get('changes')[0];
        static::assertSame('***', $change['new']);
        static::assertSame('***', $change['old']);
        static::assertTrue($change['masked']);
        static::assertStringNotContainsString('super-secret-value', json_encode($entries[0]->get('changes'), \JSON_THROW_ON_ERROR));
    }

    public function testDeletedValueGivesNullNew(): void
    {
        $this->systemConfig->set(self::DOMAIN . '.level', null);

        $entries = $this->entriesFor(self::DOMAIN . '|global');
        static::assertCount(1, $entries);
        static::assertSame('info', $entries[0]->get('changes')[0]['old']);
        static::assertNull($entries[0]->get('changes')[0]['new']);
    }

    public function testNotTrackedGivesNoEntry(): void
    {
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_CHANGE_LOG_ENTITIES->value, ['product']);
        $this->clearEntries();
        $this->systemConfig->set(self::DOMAIN . '.level', 'debug');

        static::assertSame([], $this->allEntries());
    }

    public function testInactiveGivesNoEntry(): void
    {
        $this->systemConfig->set(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value, false);
        $this->clearEntries();
        $this->systemConfig->set(self::DOMAIN . '.level', 'debug');

        static::assertSame([], $this->allEntries());
    }

    public function testCliContextIsSystemSource(): void
    {
        $this->systemConfig->set(self::DOMAIN . '.level', 'debug');

        $entries = $this->entriesFor(self::DOMAIN . '|global');
        static::assertCount(1, $entries);
        static::assertSame('system', $entries[0]->get('sourceType'));
    }

    public function testRequestContextWithAdminUserIsUserSource(): void
    {
        $userId = $this->createUser();
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);
        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, new Context($source));
        /** @var RequestStack $stack */
        $stack = static::getContainer()->get('request_stack');
        $stack->push($request);

        try {
            $this->systemConfig->set(self::DOMAIN . '.level', 'debug');
        } finally {
            $stack->pop();
        }

        $entries = $this->entriesFor(self::DOMAIN . '|global');
        static::assertCount(1, $entries);
        static::assertSame('user', $entries[0]->get('sourceType'));
        static::assertSame($userId, $entries[0]->get('userId'));
    }

    private function createUser(): string
    {
        $id = Uuid::randomHex();
        $localeId = static::getContainer()->get(Connection::class)
            ->fetchOne("SELECT LOWER(HEX(id)) FROM locale WHERE code = 'en-GB'");
        static::getContainer()->get('user.repository')->create([[
            'id' => $id,
            'localeId' => $localeId,
            'username' => 'syscfg-' . $id,
            'password' => 'initial-password-123',
            'firstName' => 'Config',
            'lastName' => 'Changer',
            'email' => $id . '@example.com',
        ]], Context::createDefaultContext());

        return $id;
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
}
