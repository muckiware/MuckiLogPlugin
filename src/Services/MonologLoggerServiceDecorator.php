<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2025 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Services;

use http\Message;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Uuid\Uuid;

use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Core\BuildLoggerSetup;
use MuckiLogPlugin\Services\SettingsInterface as PluginSettings;
use MuckiLogPlugin\Logging\LoggerInterface as MuckiLogger;
use MuckiLogPlugin\Entity\LoggerSetup;
use MuckiLogPlugin\Services\LoggerServiceBasic;

class MonologLoggerServiceDecorator extends LoggerServiceBasic implements LoggerInterface
{
    protected LoggerInterface $originalLoggerService;

    public function __construct(
        protected LoggerInterface $loggerService,
        protected MuckiLogger $muckiLogger,
        protected PluginSettings $pluginSettings,
        protected BuildLoggerSetup $buildLoggerSetup
    )
    {}

    public function createLoggerSetup(LogLevel $logLevel, mixed $message, array $context=[]): LoggerSetup
    {
        return $this->buildLoggerSetup->getLoggerSetup($logLevel, $message, $context);
    }
}
