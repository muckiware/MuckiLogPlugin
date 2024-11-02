<?php
/**
 * @category   Muckiware
 * @package    MuckiLog
 * @copyright  Copyright (c) 2023-2024 by Muckiware
 * @author     Muckiware
 */
namespace MuckiLogPlugin\Schedules;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Context;

use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Services\SendNotification as ServiceSendNotification;

#[AsMessageHandler(handles: SendNotificationLogsTask::class)]
class SendNotificationLogsTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        protected LoggerInterface $logger,
        protected PluginSettings $pluginSettings,
        protected ServiceSendNotification $serviceSendNotification
    )
    {
        parent::__construct($scheduledTaskRepository, $logger);
    }
    public static function getHandledMessages(): iterable
    {
        return [ SendNotificationLogsTask::class ];
    }

    public function run(): void
    {
        if($this->pluginSettings->isNotificationMailEnabled()) {
            $this->serviceSendNotification->sendNotificationViaSchedule(Context::createDefaultContext());
        }
    }
}
