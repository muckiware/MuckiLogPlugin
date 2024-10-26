<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Services;

use http\Message;
use Psr\Log\LoggerInterface;

use MuckiLogPlugin\Core\Defaults;
use MuckiLogPlugin\Services\SettingsInterface as PluginSettings;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Entity\LoggerSetup;

class LoggerServiceDecorator implements LoggerInterface
{
    const DEFAULT_SW_CONTEXT = 'sw';
    const DEFAULT_SW_EXTENSION = 'dev';

    private LoggerInterface $originalLoggerService;

    protected $muckiLogger;

    public function __construct(
        LoggerInterface $loggerService,
        \MuckiLogPlugin\Logging\LoggerInterface $muckiLogger,
        protected PluginSettings $pluginSettings
    )
    {
        $this->originalLoggerService = $loggerService;
        $this->muckiLogger = $muckiLogger;
    }

    public function emergency(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::CRITICAL, $message, $context));
    }

    public function alert(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::WARNING, $message, $context));
    }

    public function critical(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::CRITICAL, $message, $context));
    }

    public function error(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::ERROR, $message, $context));
    }

    public function warning(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::WARNING, $message, $context));
    }

    public function notice(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::INFO, $message, $context));
    }

    public function info(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::INFO, $message, $context));
    }

    public function debug(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::DEBUG, $message, $context));
    }

    public function log($level, mixed $message, array $context=[]): void
    {
        $this->muckiLogger->logItem($this->getLoggerSetup(LogLevel::DEBUG, $message, $context));
    }

    public function getLoggerSetup(LogLevel $logLevel, mixed $message, array $context=[]): LoggerSetup
    {
        $loggerSetup = new LoggerSetup();
        $loggerSetup->setLogLevel($logLevel->value);
        $loggerSetup->setMessage($this->inputMessageFilter($message));

        if(!empty($context) && count($context) >= 2) {

            $loggerSetup->setVendor($context[0]);
            $loggerSetup->setPlugin($context[1]);
        } else {

            $loggerSetup->setVendor(Defaults::DEFAULT_SW_CONTEXT);
            $loggerSetup->setPlugin(Defaults::DEFAULT_SW_EXTENSION);
        }

        $loggerSetup->setNotificationEmailTemplateId('0192b52625b073278270081899140df7');
        $loggerSetup->setNotificationEmailSender('freyda@muster.com');
        $loggerSetup->setNotificationEmailReceiver('torsten@muster.com');

        $loggerSetup->setSendNotification($this->pluginSettings->needNotificationByLogLevel($logLevel));

        return $loggerSetup;
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
}
