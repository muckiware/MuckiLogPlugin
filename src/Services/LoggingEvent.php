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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;

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
                'loglevel' => $loggerSetup->getLoglevel()->value,
                'message' => $loggerSetup->getMessage(),
                'notificationEmailTemplateId' => $loggerSetup->getNotificationEmailTemplateId(),
                'notificationEmailReceiver' => $loggerSetup->getNotificationEmailReceiver(),
                'notificationEmailSender' => $loggerSetup->getNotificationEmailSender(),
                'created_at' => new \DateTime('now', new \DateTimeZone('UTC'))
            ],
        ], Context::createDefaultContext());
    }

    public function getLoggerEvents(): EntitySearchResult
    {
        $criteria = new Criteria();
        $criteria->setLimit(5);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));

        return $this->loggingEventRepository->search($criteria, Context::createDefaultContext());
    }
}
