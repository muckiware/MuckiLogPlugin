<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

use MuckiLogPlugin\Entity\LoggerSetup;

class LoggingEvent
{
    public function __construct(
        protected EntityRepository $loggingEventRepository
    )
    {}

    public function saveEvent(LoggerSetup $loggerSetup): void
    {
        $this->loggingEventRepository->create([
            [
                'id' => Uuid::randomHex(),
                'vendor' => $loggerSetup->getVendor(),
                'plugin' => $loggerSetup->getPlugin(),
                'loglevel' => $loggerSetup->getLoglevel(),
                'message' => $loggerSetup->getMessage(),
                'notificationEmailTemplateId' => $loggerSetup->getNotificationEmailTemplateId(),
                'notificationEmailReceiver' => $loggerSetup->getNotificationEmailReceiver(),
                'notificationEmailSender' => $loggerSetup->getNotificationEmailSender(),
                'created_at' => new \DateTime('now', new \DateTimeZone('UTC'))
            ],
        ], Context::createDefaultContext());
    }
}
