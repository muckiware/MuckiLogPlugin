<?php
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\Logging;

use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Entity\LoggerSetup;
use MuckiLogPlugin\Services\SettingsInterface;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Services\LoggingEvent;
use MuckiLogPlugin\log4php\Logger as Log4phpLogger;

/**
 * @package MuckiLogPlugin\Logging
 *
 */
class Logger implements LoggerInterface
{
    /**
     * @var Log4phpLogger
     */
	protected Log4phpLogger $logger;

	
	public function __construct(
        protected LogconfigInterface $logConfig,
        protected SettingsInterface $settings,
        protected LoggingEvent $loggingEvent
	) {
	    $this->logger = $this->logConfig->getLogger();
	}

    public function executeLoggingByLogLevel(LoggerSetup $loggerSetup): void
    {
        switch ($loggerSetup->getLogLevel()) {

            default:
                $this->logger->debug($loggerSetup->getMessage());
                break;
            case LogLevel::INFO;
                $this->logger->info($loggerSetup->getMessage());
                break;
            case LogLevel::WARNING:
                $this->logger->warning($loggerSetup->getMessage());
                break;
            case LogLevel::ERROR:
                $this->logger->error($loggerSetup->getMessage());
                break;
            case LogLevel::CRITICAL:
                $this->logger->critical($loggerSetup->getMessage());
                break;
        }

        if($loggerSetup->isSendNotification()) {
            $this->loggingEvent->saveEvent($loggerSetup);
        }
    }

	public function logItem(LoggerSetup $loggerSetup): void
    {
	    if($this->settings->isEnabled() && $this->setLoggerConfig($loggerSetup)) {
            $this->executeLoggingByLogLevel($loggerSetup);
		}
	}

    /**
     * Method for to load a xml config file, if it already exists, otherwise if will create a new config file.
     *
     * @param LoggerSetup $loggerSetup
     * @return boolean
     */
	protected function setLoggerConfig(LoggerSetup $loggerSetup): bool
    {
		if($this->logConfig->checkConfigPath($loggerSetup->getVendor(), $loggerSetup->getPlugin())) {

		    $this->logger->configure($this->settings->getConfigPath($loggerSetup->getVendor(), $loggerSetup->getPlugin()));
		    return true;
		}

        return false;
	}
}
