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

use MuckiLogPlugin\Core\SendMailMode;
use MuckiLogPlugin\Entity\LoggerSetup;
use MuckiLogPlugin\Services\Settings as PluginSettings;

class LoggingEvent
{
    public function __construct(
        protected EntityRepository $loggingEventRepository,
        protected PluginSettings $pluginSettings
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

    public function getLoggerEvents(?string $loglevel=null): EntitySearchResult
    {
        return $this->loggingEventRepository->search(
            $this->getCriteriaBySendMailMode($this->pluginSettings->getSendMailMode(), $loglevel),
            Context::createDefaultContext()
        );
    }

    public function removeLoggerEventById(string $loggerEventById): void
    {
        $loggerEventByIds = array_map(
            static fn ($id) => [ 'id' => $id ],
            [$loggerEventById],
        );
        $this->loggingEventRepository->delete(array_values($loggerEventByIds), Context::createDefaultContext());
    }

    public function getCriteriaBySendMailMode(string $sendMailMode, string $loglevel=null): Criteria
    {
        $criteria = new Criteria();
        switch ($sendMailMode) {

            case SendMailMode::EventMail->value:
                $criteria = $this->getCriteriaEventMail();
                break;
            case SendMailMode::LogLevelMail->value:
                if($loglevel) {
                    $criteria = $this->getCriteriaLogLevelMail($loglevel);
                }
                break;
            default:
                //TODO throw error
                break;
        }

        return $criteria;
    }

    public function getCriteriaEventMail(): Criteria
    {
        $criteria = new Criteria();
        $criteria->setLimit(5);
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('vendor', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('plugin', FieldSorting::ASCENDING));

        return $criteria;
    }

    public function getCriteriaLogLevelMail(string $logLevel): Criteria
    {
        $criteria = new Criteria();
        $criteria->setLimit(100);
        $criteria->addFilter(new EqualsFilter('loglevel', $logLevel));
        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('vendor', FieldSorting::ASCENDING));
        $criteria->addSorting(new FieldSorting('plugin', FieldSorting::ASCENDING));

        return $criteria;
    }
}
