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

/**
 * @package MuckiLogPlugin\Logging
 *
 */
class Logger implements LoggerInterface
{
    /**
     * @var \MuckiLogPlugin\log4php\Logger
     */
	protected \MuckiLogPlugin\log4php\Logger $logger;

	
	public function __construct(
        protected LogconfigInterface $logConfig,
        protected SettingsInterface $settings
	) {
	    $this->logger = $this->logConfig->getLogger();
	}
	
	/**
	 * Log into debugging level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function debugItem($message, string $loggerContext = '', string $extensionContext = ''): void
    {
	    if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->logger->debug($this->inputMessageFilter($message));
		}
	}
	
	/**
	 * Log into critical level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function criticalItem($message, $loggerContext = '', $extensionContext = ''): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->logger->critical($this->inputMessageFilter($message));
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
	public function infoItem($message, $loggerContext = '', $extensionContext = ''): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->logger->info($this->inputMessageFilter($message));
		}
	}
	
	/**
	 * Log into error level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function errorItem(mixed $message, $loggerContext = '', $extensionContext = ''): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->logger->error($this->inputMessageFilter($message));
		}
	}
	
	/**
	 * Log into warning level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function warningItem(mixed $message, $loggerContext = '', $extensionContext = ''): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->logger->warning($this->inputMessageFilter($message));
		}
	}

    public function warnItem(mixed $message, $loggerContext = '', $extensionContext = ''): void
    {
        if($this->settings->isEnabled() && $this->setLoggerConfig($loggerContext, $extensionContext)) {
            $this->logger->warning($this->inputMessageFilter($message));
        }
    }

    public function inputMessageFilter(mixed $message)
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
