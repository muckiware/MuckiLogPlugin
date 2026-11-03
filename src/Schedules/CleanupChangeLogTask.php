<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Schedules;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class CleanupChangeLogTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'muwa.cleanup_change_log_task';
    }

    public static function getDefaultInterval(): int
    {
        return 86400;
    }
}
