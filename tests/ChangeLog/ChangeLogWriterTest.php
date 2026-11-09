<?php

declare(strict_types=1);

namespace MuckiLogPlugin\tests\ChangeLog;

use MuckiLogPlugin\ChangeLog\ChangeLogWriter;
use MuckiLogPlugin\ChangeLog\EntityLabelResolver;
use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\ChangeLogCommit;
use MuckiLogPlugin\ChangeLog\Struct\ChangeSource;
use MuckiLogPlugin\ChangeLog\Struct\ChangeSourceType;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ChangeLogWriterTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOverlongStringsAreTruncatedTo255(): void
    {
        $container = static::getContainer();
        $repository = $container->get('muwa_admin_change_log.repository');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::never())->method('error');
        $writer = new ChangeLogWriter($repository, $container->get(EntityLabelResolver::class), $logger);

        $entityId = 'MuckiLong.config|' . str_repeat('e', 300);
        $commit = new ChangeLogCommit('system_config', $entityId, ChangeAction::Update, [new FieldChange('a', null, [], 1, 2)], [], 'Label');
        $source = new ChangeSource(ChangeSourceType::System, null, str_repeat('ü', 300), null, null, str_repeat('s', 300), null);

        $writer->write([$commit], $source, Context::createDefaultContext());

        $criteria = (new Criteria())->addFilter(new EqualsFilter('entityId', mb_substr($entityId, 0, 255)));
        $entry = $repository->search($criteria, Context::createDefaultContext())->first();
        static::assertNotNull($entry);
        static::assertSame(str_repeat('ü', 255), $entry->get('userName'));
        static::assertSame(str_repeat('s', 255), $entry->get('sourceName'));
    }
}
