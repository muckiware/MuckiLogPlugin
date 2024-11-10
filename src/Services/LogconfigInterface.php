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

use MuckiLogPlugin\Log4php\Logger;

interface LogconfigInterface
{
    /**
     * Method for to get an php4log logger object
     *
     */
    public function getLogger(): Logger;

    /**
     * Method for to load a xml config file, if it already exists.
     *
     * @param string $loggerContext
     * @param string $extensionContext
     * @return boolean
     */
    public function checkConfigPath(string $loggerContext='', string $extensionContext=''): bool;
    
    /**
     * Method for to remove obsolete logger config files
     *
     * @param string $path
     */
    public function removeLogConfigFiles(string $path): void;
}

