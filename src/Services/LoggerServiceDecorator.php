<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021 by Muckiware
 *
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Services;

use http\Message;
use Psr\Log\LoggerInterface;
use MuckiLogPlugin\Services\SettingsInterface;

class LoggerServiceDecorator implements LoggerInterface {

    const DEFAULT_SW_CONTEXT = 'sw';
    const DEFAULT_SW_EXTENSION = 'dev';

    private $originalLoggerSerivce;

    protected $muckiLogger;

    public function __construct(
        LoggerInterface $loggerService,
        \MuckiLogPlugin\Logging\LoggerInterface $muckiLogger
    ) {
        $this->originalLoggerSerivce = $loggerService;
        $this->muckiLogger = $muckiLogger;
    }

    public function emergency($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->fatalItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->fatalItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function alert($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->warnItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->warnItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function critical($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->fatalItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->fatalItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function error($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->errorItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->errorItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function warning($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->warnItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->warnItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function notice($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->warnItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->warnItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function info($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->infoItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->infoItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function debug($message, array $context = array()) {

        if(!empty($context)) {
            $this->muckiLogger->debugItem($message, $context[0], $context[1]);
        } else {
            $this->muckiLogger->debugItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
        }
    }

    public function log($level, $message, array $context = array()) {

        switch($level) {

            case 'debug':
                if(!empty($context)) {
                    $this->muckiLogger->debugItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->debugItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
                }
                break;

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
                    $this->muckiLogger->warnItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->warnItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
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
                    $this->muckiLogger->fatalItem($message, $context[0], $context[1]);
                } else {
                    $this->muckiLogger->fatalItem($message, self::DEFAULT_SW_CONTEXT, self::DEFAULT_SW_EXTENSION);
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
