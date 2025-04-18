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
use Shopware\Core\Framework\Uuid\Uuid;

use MuckiLogPlugin\Core\BuildLoggerSetup;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Services\SettingsInterface as PluginSettings;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLogger;

abstract class LoggerServiceBasic
{
    public function emergency(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::CRITICAL, $message, $context));
    }

    public function alert(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::WARNING, $message, $context));
    }

    public function critical(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::CRITICAL, $message, $context));
    }

    public function error(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::ERROR, $message, $context));
    }

    public function warning(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::WARNING, $message, $context));
    }

    public function notice(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::INFO, $message, $context));
    }

    public function info(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::INFO, $message, $context));
    }

    public function debug(mixed $message, array $context = array()): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::DEBUG, $message, $context));
    }

    public function log($level, mixed $message, array $context=[]): void
    {
        $this->muckiLogger->logItem($this->createLoggerSetup(LogLevel::DEBUG, $message, $context));
    }
}
