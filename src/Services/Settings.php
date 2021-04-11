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

use Symfony\Component\HttpKernel\KernelInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Settings implements SettingsInterface {
    
    const LOGGER_PATH = '/custom/plugins/MuckiLogPlugin/src/log4php/Logger.php';
    
    const LOGGER_CONFIG_PATH = '/custom/plugins/MuckiLogPlugin/src/Resources/config';
    
    const MAXBACKUPINDEX = 10;
    
    const CONVERSIONPATTERN = '%date{Y-m-d H:i:s,u} [%t] %-5p (%C) %m%n%ex';
    
    const CONFIG_PATH_ACTIVE = 'MuckiLogPlugin.config.active';
    const CONFIG_PATH_LOG_LEVEL = 'MuckiLogPlugin.config.level';
    const CONFIG_PATH_MAX_BACKUP_INDEX = 'MuckiLogPlugin.config.maxbackupindex';
    const CONFIG_PATH_MAX_FILESIZE = 'MuckiLogPlugin.config.maxfilesize';
    
    //Default values
    const CONFIG_PATH_LOG_LEVEL_DEFAULT = 'info';
    const CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT = '10';
    const CONFIG_PATH_MAX_FILESIZE_DEFAULT = '10MB';
    
    const PLUGIN_ROOT_PATH = '/custom/plugins/MuckiLogPlugin/src';
    
    /**
     * Absolute path to folder of log config files
     * @var string
     */
    protected $_logConfigPath;

    public function __construct(
        SystemConfigService $config,
        KernelInterface $kernel
    ) {
        
        $this->_config = $config;
        $this->kernel = $kernel;
    }
    
    public function isEnabled() {
        return $this->_config->get($this::CONFIG_PATH_ACTIVE);
    }

    public function getPluginConfig() {
        
        return $this->_config;
    }

    public function getLogPath(): string {
        return $this->kernel->getProjectDir().'/var/log';
    }
    
    public function getLogConfigPath(): string {
        
        if(!$this->_logConfigPath || $this->_logConfigPath === '') {
            $this->_logConfigPath = $this->kernel->getProjectDir().$this::LOGGER_CONFIG_PATH;
        }

        return $this->_logConfigPath;
    }
    
    public function getConfigPath($loggerContext = '', $extensionContext = ''): string {

        switch (true) {
            case ($loggerContext !== '' && $extensionContext !== ''):
                return $this->getLogConfigPath().'/logconfig.'.$extensionContext.'.'.$loggerContext.'.xml';
            case ($loggerContext !== '' && $extensionContext == ''):
                return $this->getLogConfigPath().'/logconfig.'.$loggerContext.'.xml';
            case ($loggerContext == '' && $extensionContext !== ''):
                return $this->getLogConfigPath().'/logconfig.'.$extensionContext.'.xml';
            default:
                return $this->getLogConfigPath().'/logconfig.xml';
        }
    }
    
    public function getMaxBackupIndex(): string {

        if($this->_config->get($this::CONFIG_PATH_MAX_BACKUP_INDEX) != '') {
            return $this->_config->get($this::CONFIG_PATH_MAX_BACKUP_INDEX);
        } else {
            return $this::CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT;
        }
    }
    
    public function getMaxFileSize(): string {
        
        if($this->_config->get($this::CONFIG_PATH_MAX_FILESIZE) != '') {
            return $this->_config->get($this::CONFIG_PATH_MAX_FILESIZE).'MB';
        } else {
            return $this::CONFIG_PATH_MAX_FILESIZE_DEFAULT;
        }
    }
    
    public function getLoglevel(): string {
        
        if($this->_config->get($this::CONFIG_PATH_LOG_LEVEL) != '') {
            return $this->_config->get($this::CONFIG_PATH_LOG_LEVEL);
        } else {
            return $this::CONFIG_PATH_LOG_LEVEL_DEFAULT;
        }
    }
    
    public function getLoggerFileName($loggerContext = '', $extensionContext = ''): string {
        
        switch (true) {
            case ($loggerContext !== '' && $extensionContext !== ''):
                return $this->getLogPath().'/'.$extensionContext.'.'.$loggerContext.'.log';
            case ($loggerContext !== '' && $extensionContext == ''):
                return $this->getLogPath().'/muckilog.'.$loggerContext.'.log';
            case ($loggerContext == '' && $extensionContext !== ''):
                return $this->kernel->getLogDir().'/'.$extensionContext.'.log';
            default:
                return $this->kernel->getLogDir().'/muckilog.log';
        }
    }
}

