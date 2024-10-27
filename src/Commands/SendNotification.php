<?php
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Commands;

use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Shopware\Core\Framework\Context;

use MuckiLogPlugin\Core\Defaults as PluginDefaults;
use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLoggerInterface;
use MuckiLogPlugin\Services\SendNotification as ServiceSendNotification;

class SendNotification extends Command
{
    protected LogconfigInterface $logconfig;
    protected MuckiLoggerInterface $muckilogLogger;
    /**
     * @var string
     */
    public static $defaultName = 'muckiware:logger:send';

    protected ?ContainerInterface $container = null;

    public function __construct(
        protected LoggerInterface $logger,
        protected PluginSettings $pluginSettings,
        protected LogconfigInterface $logconfigInterface,
        protected MuckiLoggerInterface $muckiLogger,
        protected ServiceSendNotification $serviceSendNotification
    )
    {
        parent::__construct(self::$defaultName);
        $this->logconfig = $logconfigInterface;
        $this->muckilogLogger = $muckiLogger;
    }

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @internal
     */
    public function configure()
    {
        $this->setDescription('This Muckilog plugin command for to send logger events by email');
        parent::configure();
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Exception
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Start to send logger events');

        $this->serviceSendNotification->sendNotification($output, Context::createDefaultContext());
        $output->writeln('Send logger events is done');

        return 0;
    }
}
