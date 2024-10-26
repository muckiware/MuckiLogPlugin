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

    public function executeLoggingByLogLevel(
        string $logLevel,
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
        $messageInput = $this->inputMessageFilter($message);

        switch ($logLevel) {

            default:
                $this->logger->debug($messageInput);
                break;
            case 'info':
                $this->logger->info($messageInput);
                break;
            case 'warning':
                $this->logger->warning($messageInput);
                break;
            case 'error':
                $this->logger->error($messageInput);
                break;
            case 'critical':
                $this->logger->critical($messageInput);
                break;
        }

        if($this->settings->isDebugNotification() || $notification) {
            $this->loggingEvent->saveEvent($logLevel, $loggerContext, $extensionContext, $messageInput);
        }
    }

    /**
     * Log into debugging level
     *
     * @param string $message
     * @param string $loggerContext , usually name of plugin
     * @param string $extensionContext , like name of plugin vendor
     * @param bool $notification
     * @return void
     */
	public function debugItem(
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
	    if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->executeLoggingByLogLevel('debug', $message, $loggerContext, $extensionContext);
		}
	}

    /**
     * Log into critical level
     *
     * @param string $message
     * @param string $loggerContext , usually name of plugin
     * @param string $extensionContext , like name of plugin vendor
     * @param bool $notification
     * @return void
     */
	public function criticalItem(
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->executeLoggingByLogLevel('critical', $message, $loggerContext, $extensionContext);
        }
	}
	
	/**
	 * Log into info level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function infoItem(
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->executeLoggingByLogLevel('info', $message, $loggerContext, $extensionContext);
        }
	}

    /**
     * Log into error level
     *
     * @param string $message
     * @param string $loggerContext , usually name of plugin
     * @param string $extensionContext , like name of plugin vendor
     * @param bool $notification
     * @return void
     */
	public function errorItem(
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->executeLoggingByLogLevel('error', $message, $loggerContext, $extensionContext);
        }
	}

    /**
     * Log into warning level
     *
     * @param string $message
     * @param string $loggerContext , usually name of plugin
     * @param string $extensionContext , like name of plugin vendor
     * @param bool $notification
     * @return void
     */
	public function warningItem(
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->executeLoggingByLogLevel('warning', $message, $loggerContext, $extensionContext);
        }
	}

    public function warnItem(
        mixed $message,
        string $loggerContext='',
        string $extensionContext='',
        bool $notification=false
    ): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->executeLoggingByLogLevel('warning', $message, $loggerContext, $extensionContext);
        }
    }

    public function inputMessageFilter(mixed $message): string
    {
        if(is_array($message) || is_object($message)) {
            return print_r($message, true);
        }

        if(is_int($message) || is_float($message)) {
            return strval($message);
        }

        return $message;
    }

    /**
     * Method for to load a xml config file, if it already exists, otherwise if will create a new config file.
     *
     * @param string $loggerContext
     * @param string $extensionContext
     * @return boolean
     */
	protected function setLoggerConfig(string $loggerContext = '', string $extensionContext = ''): bool
    {
		if($this->logConfig->checkConfigPath($loggerContext, $extensionContext)) {

		    $this->logger->configure($this->settings->getConfigPath($loggerContext, $extensionContext));
		    return true;
		}

        return false;
	}
}
