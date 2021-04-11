<?php 
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

interface SettingsInterface {
    
    public function isEnabled();

    public function getPluginConfig();

    public function getLogPath(): string;
    
    public function getLogConfigPath(): string;
    
    public function getConfigPath($loggerContext = '', $extensionContext = ''): string;
    
    public function getMaxBackupIndex(): string;
    
    public function getMaxFileSize(): string;
    
    public function getLoglevel(): string;
    
    public function getLoggerFileName($loggerContext = '', $extensionContext = ''): string;
}

