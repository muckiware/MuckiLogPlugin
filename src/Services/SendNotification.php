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

use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Context;

use MuckiLogPlugin\Services\LoggingEvent;
use MuckiLogPlugin\Services\CliOutput;
use MuckiLogPlugin\Core\SendMailMode;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Core\LoggingEvent\LoggingEventEntity;
use MuckiLogPlugin\Core\LoggingEvent\LoggingEventCollection;
use MuckiLogPlugin\Core\EmailNotification\EmailNotificationEntity;
use MuckiLogPlugin\Core\EmailNotification\EmailNotificationCollection;
use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Services\Mailer as ServiceMailer;

class SendNotification
{
    public function __construct(
        protected LoggingEvent $loggingEvent,
        protected CliOutput $cliOutput,
        protected ServiceMailer $serviceMailer,
        protected PluginSettings $pluginSettings
    )
    {}

    public function sendNotification(OutputInterface $cliOutput, Context $context): void
    {
        $emailNotificationCollection = $this->getEmailNotificationCollection($context);
        $totalCounter = $emailNotificationCollection->count();
        $this->cliOutput->printCliOutput($cliOutput, 'Found '.$totalCounter.' mail notifications.');
        $progress = $this->cliOutput->prepareSendProgress($totalCounter);
        $progressBar = $this->cliOutput->prepareSendProgressBar($progress, $totalCounter, $cliOutput);

        if($totalCounter >= 1) {

            foreach ($emailNotificationCollection->getElements() as $emailNotification) {

                if ($progress->getTotal() && $progress->getOffset() >= $progress->getTotal()) {
                    $progressBar->setProgress($progress->getTotal());
                } else {
                    $progressBar->advance();
                    $progressBar->display();
                }

                $this->serviceMailer->sendMailNotification($emailNotification);
//                $this->loggingEvent->removeLoggerEventById($logEvent->getId());
            }

            $progressBar->finish();
            $cliOutput->writeln('');
        }
    }

    public function getEmailNotificationCollection(Context $context): EmailNotificationCollection
    {
        $emailNotificationCollection = null;
        switch ($this->pluginSettings->getSendMailMode()) {

            case SendMailMode::EventMail->value:
                $emailNotificationCollection = $this->getMailSetupCollectionByEvent($context);
                break;
            case SendMailMode::LogLevelMail->value:
                $emailNotificationCollection = $this->getMailSetupCollectionByLoglevel($context);
                break;
            default:
                //TODO throw error
                break;
        }

        return $emailNotificationCollection;
    }
    public function getMailSetupCollectionByEvent(Context $context): EmailNotificationCollection
    {
        $mailNotificationCollection = new EmailNotificationCollection();
        $logEvents = $this->loggingEvent->getLoggerEvents();
        /** @var LoggingEventEntity $logEvent */
        foreach ($logEvents as $logEvent) {

            $mailNotification = new EmailNotificationEntity();
            $mailNotification->setId(Uuid::randomHex());
            $mailNotification->setLoglevel($logEvent->getLoglevel());
            $mailNotification->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
            $mailNotification->setContext($context);
            $mailNotification->setEmailParameter($this->serviceMailer->buildEmailParameterByEvent($logEvent, $context));

            $loggingEventCollection = new LoggingEventCollection();
            $loggingEventCollection->add($logEvent);
            $mailNotification->setLoggingEventCollection($loggingEventCollection);

            $mailNotificationCollection->add($mailNotification);
        }

        return $mailNotificationCollection;
    }

    public function getMailSetupCollectionByLoglevel(Context $context): EmailNotificationCollection
    {
        $mailNotificationCollection = new EmailNotificationCollection();
        foreach (LogLevel::cases() as $loggingMethod) {

            $logEvents = $this->loggingEvent->getLoggerEvents($loggingMethod->value);

            $mailNotification = new EmailNotificationEntity();
            $mailNotification->setId(Uuid::randomHex());
            $mailNotification->setLoglevel($loggingMethod->value);
            $mailNotification->setCreatedAt(new \DateTime('now', new \DateTimeZone('UTC')));
            $mailNotification->setContext($context);
            $mailNotification->setEmailParameter($this->serviceMailer->buildEmailParameterByLoglevel($context));
            $mailNotification->setLoggingEventCollection($logEvents->getEntities());

            $mailNotificationCollection->add($mailNotification);
        }

        return $mailNotificationCollection;
    }
}
