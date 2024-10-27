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

use Symfony\Component\Console\Output\OutputInterface;
use Shopware\Core\Framework\Context;

use MuckiLogPlugin\Services\LoggingEvent;
use MuckiLogPlugin\Services\CliOutput;
use MuckiLogPlugin\Services\Mailer as ServiceMailer;
use MuckiLogPlugin\Core\LoggingEvent\LoggingEventEntity;
class SendNotification
{
    public function __construct(
        protected LoggingEvent $loggingEvent,
        protected CliOutput $cliOutput,
        protected ServiceMailer $serviceMailer,
    )
    {}

    public function sendNotification(OutputInterface $cliOutput, Context $context): void
    {
        $logEvents = $this->loggingEvent->getLoggerEvents();
        $totalCounter = $logEvents->count();
        $this->cliOutput->printCliOutput($cliOutput, 'Found '.$totalCounter.' log events.');
        $progress = $this->cliOutput->prepareSendProgress($totalCounter);
        $progressBar = $this->cliOutput->prepareSendProgressBar($progress, $totalCounter, $cliOutput);

        if($logEvents->count() >= 1) {

            /** @var LoggingEventEntity $logEvent */
            foreach ($logEvents as $logEvent) {

                if ($progress->getOffset() >= $progress->getTotal()) {
                    $progressBar->setProgress($progress->getTotal());
                } else {
                    $progressBar->advance();
                    $progressBar->display();
                }

                $this->serviceMailer->sendMailNotification($logEvent, $context);
            }

            $progressBar->finish();
            $cliOutput->writeln('');
        }
    }
}
