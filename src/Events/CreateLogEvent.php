<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2025 by muckiware
 *
 */
namespace MuckiLogPlugin\Events;

use Symfony\Contracts\EventDispatcher\Event;

use MuckiLogPlugin\Entity\LoggerSetup;

class CreateLogEvent extends Event
{
    public function __construct(
        protected LoggerSetup $loggerSetup
    )
    {}

    public function getLoggerSetup(): LoggerSetup
    {
        return $this->loggerSetup;
    }
}
