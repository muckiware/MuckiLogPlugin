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
use MuckiLogPlugin\Services\SettingsInterface;

class LoggerServiceDecorator implements LoggerInterface
{
    const DEFAULT_SW_CONTEXT = 'sw';
    const DEFAULT_SW_EXTENSION = 'dev';

    private LoggerInterface $originalLoggerService;

    protected $muckiLogger;

    public function __construct(
        LoggerInterface $loggerService,
        \MuckiLogPlugin\Logging\LoggerInterface $muckiLogger
    )
    {
        $this->originalLoggerService = $loggerService;
        $this->muckiLogger = $muckiLogger;
    }

    public function emergency(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->criticalItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->criticalItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function alert(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->warningItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->warningItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function critical(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->criticalItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->criticalItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function error(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->errorItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->errorItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function warning(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->warningItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->warningItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function notice(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->warningItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->warningItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function info(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->infoItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->infoItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function debug(mixed $message, array $context = array()): void
    {
        if(!empty($context)) {
            $this->muckiLogger->debugItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->debugItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function log($level, string|\Stringable $message, array $context = array()): void
    {
        switch($level) {

            case 'info':
                if(!empty($context)) {
                    $this->muckiLogger->infoItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->infoItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
                }
                break;

            case 'notice':
            case'warning':
            case 'alert':
                if(!empty($context)) {
                    $this->muckiLogger->warningItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->warningItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
                }
                break;

            case 'error':
                if(!empty($context)) {
                    $this->muckiLogger->errorItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->errorItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
                }
                break;

            case 'critical':
            case 'emergency':
                if(!empty($context)) {
                    $this->muckiLogger->criticalItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->criticalItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
                }
                break;

            default:
                if(!empty($context)) {
                    $this->muckiLogger->debugItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->debugItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
                }
                break;
        }
    }
}
