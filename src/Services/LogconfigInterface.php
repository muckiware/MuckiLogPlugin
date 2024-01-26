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

interface LogconfigInterface
{
    /**
     * Method for to get an php4log logger object
     * 
     */
    public function getLogger();

    /**
     * Method for to load a xml config file, if it already exists.
     *
     * @param string $loggerContext
     * @return boolean
     */
    public function checkConfigPath($loggerContext = '', $extensionContext = ''): bool;
    
    /**
     * Method for to remove obsolete logger config files
     * 
     * @param string $path
     */
    public function removeLogConfigFiles($path);
}

