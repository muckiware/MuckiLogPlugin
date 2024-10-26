<?php
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 *
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Logging;

use MuckiLogPlugin\Entity\LoggerSetup;

/**
 * @package MuckiLogPlugin\Logging
 *
 */
interface LoggerInterface
{
    public function logItem(LoggerSetup $loggerSetup): void;
}