<?php
/**
 * @category   Muckiware
 * @package    MuckiLog
 * @copyright  Copyright (c) 2023-2024 by Muckiware
 * @author     Muckiware
 */
namespace MuckiLogPlugin\Schedules;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

use MuckiLogPlugin\Core\Defaults as PluginDefaults;

class SendNotificationLogsTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'muwa.send_notification_logs_task';
    }

    public static function getDefaultInterval(): int
    {
        return PluginDefaults::DEFAULT_TASK_INTERVAL_IN_SECONDS;
    }
}
