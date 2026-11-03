<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Schedules;

use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: CleanupChangeLogTask::class)]
class CleanupChangeLogTaskHandler extends ScheduledTaskHandler
{
    private const BLOCK_SIZE = 1000;

    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $logger,
        private readonly ChangeLogConfig $changeLogConfig,
        private readonly Connection $connection
    ) {
        parent::__construct($scheduledTaskRepository, $logger);
    }

    /**
     * @return iterable<class-string>
     */
    public static function getHandledMessages(): iterable
    {
        return [CleanupChangeLogTask::class];
    }

    public function run(): void
    {
        if (!$this->changeLogConfig->isActive()) {
            return;
        }

        $days = $this->changeLogConfig->getRetentionDays();
        if ($days <= 0) {
            return;
        }

        $this->deleteOlderThan($days);
    }

    public function deleteOlderThan(int $days): int
    {
        $threshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s.v');

        $total = 0;
        do {
            $affected = (int) $this->connection->executeStatement(
                'DELETE FROM muwa_admin_change_log WHERE created_at < :threshold LIMIT ' . self::BLOCK_SIZE,
                ['threshold' => $threshold]
            );
            $total += $affected;
        } while ($affected === self::BLOCK_SIZE);

        return $total;
    }
}
