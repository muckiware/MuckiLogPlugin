<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace MuckiLogPlugin\Services;

use http\Message;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

use MuckiLogPlugin\Core\Defaults;
use MuckiLogPlugin\Services\SettingsInterface as PluginSettings;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Entity\LoggerSetup;
use MuckiLogPlugin\Services\Helper as PluginHelper;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLogger;

class LoggerServiceDecorator implements LoggerInterface
{
    const DEFAULT_SW_CONTEXT = 'sw';
    const DEFAULT_SW_EXTENSION = 'dev';
    protected LoggerInterface $originalLoggerService;

    public function __construct(
        LoggerInterface $loggerService,
        protected MuckiLogger $muckiLogger,
        protected PluginSettings $pluginSettings,
        protected PluginHelper $pluginHelper
    )
    {
        $this->originalLoggerService = $loggerService;
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
        $loggerSetup->setLogLevel($logLevel);
        $loggerSetup->setMessage($this->inputMessageFilter($message));
        $loggerSetup = $this->setupVendorPluginNames($context, $loggerSetup);

        $needNotification = $this->pluginSettings->needNotificationByLogLevel($logLevel) || $this->contextSetupNeedNotification($context);
        if($needNotification) {

            $loggerSetup = $this->setupNotificationEmailTemplateId($context, $loggerSetup);
            $loggerSetup = $this->setupNotificationEmailReceiver($context, $loggerSetup);
            $loggerSetup = $this->setupNotificationEmailSender($context, $loggerSetup);
        }

        $loggerSetup->setSendNotification($needNotification);
        $loggerSetup->setPathLogFile($this->pluginSettings->getConfigPath(
            $loggerSetup->getVendor(),
            $loggerSetup->getPlugin()
        ));

        return $loggerSetup;
    }

    public function contextSetupNeedNotification(array $context): bool
    {
        if (
            isset($context[2]) &&
            is_array($context[2]) &&
            array_key_exists('setup', $context[2]) &&
            array_key_exists('notificationEmail', $context[2]['setup']) &&
            is_bool($context[2]['setup']['notificationEmail'])
        ) {
            return true;
        }
        return false;
    }

    public function setupNotificationEmailTemplateId(array $context, LoggerSetup $loggerSetup): LoggerSetup
    {
        if (
            isset($context[2]) &&
            is_array($context[2]) &&
            array_key_exists('setup', $context[2]) &&
            array_key_exists('notificationEmailTemplateId', $context[2]['setup']) &&
            Uuid::isValid(trim($context[2]['setup']['notificationEmailTemplateId']))
        ) {
            $loggerSetup->setNotificationEmailTemplateId(trim($context[2]['setup']['notificationEmailTemplateId']));
        } else {

            $notificationMailTemplateId = $this->pluginSettings->getNotificationMailTemplateId();
            if($notificationMailTemplateId) {
                $loggerSetup->setNotificationEmailTemplateId($notificationMailTemplateId);
            }
        }
        return $loggerSetup;
    }

    public function setupNotificationEmailReceiver(array $context, LoggerSetup $loggerSetup): LoggerSetup
    {
        if (
            isset($context[2]) &&
            is_array($context[2]) &&
            array_key_exists('setup', $context[2]) &&
            array_key_exists('notificationEmailReceiver', $context[2]['setup']) &&
            $this->pluginHelper->isValidEmail(trim($context[2]['setup']['notificationEmailReceiver']))
        ) {
            $loggerSetup->setNotificationEmailReceiver(trim($context[2]['setup']['notificationEmailReceiver']));
        } else {

            $notificationMailAddress = $this->pluginSettings->getNotificationMailAddress();
            if($notificationMailAddress) {
                $loggerSetup->setNotificationEmailReceiver($notificationMailAddress);
            }
        }
        return $loggerSetup;
    }

    public function setupNotificationEmailSender(array $context, LoggerSetup $loggerSetup): LoggerSetup
    {
        if (
            isset($context[2]) &&
            is_array($context[2]) &&
            array_key_exists('setup', $context[2]) &&
            array_key_exists('notificationEmailSender', $context[2]['setup']) &&
            $this->pluginHelper->isValidEmail(trim($context[2]['setup']['notificationEmailSender']))
        ) {
            $loggerSetup->setNotificationEmailSender(trim($context[2]['setup']['notificationEmailReceiver']));
        } else {
            $loggerSetup->setNotificationEmailSender($this->pluginSettings->getNotificationMailSender());
        }
        return $loggerSetup;
    }

    public function setupVendorPluginNames(array $context, LoggerSetup $loggerSetup): LoggerSetup
    {
        if (isset($context[0], $context[1])) {
            $loggerSetup->setVendor(trim($context[0]));
            $loggerSetup->setPlugin(trim($context[1]));
        } else {
            $loggerSetup->setVendor(Defaults::DEFAULT_SW_CONTEXT);
            $loggerSetup->setPlugin(Defaults::DEFAULT_SW_EXTENSION);
        }
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
