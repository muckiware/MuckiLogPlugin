<?php
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

use Symfony\Component\HttpKernel\KernelInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class Settings implements SettingsInterface
{
    const LOGGER_PATH = '/log4php/Logger.php';

    const LOG_PATH = '/var/log';
    const LOGGER_CONFIG_PATH = '/Resources/config';
    
    const MAXBACKUPINDEX = 10;

    const CONFIG_PATH_ACTIVE = 'MuckiLogPlugin.config.active';
    const CONFIG_PATH_LOG_LEVEL = 'MuckiLogPlugin.config.level';
    const CONFIG_PATH_MAX_BACKUP_INDEX = 'MuckiLogPlugin.config.maxbackupindex';
    const CONFIG_PATH_MAX_FILESIZE = 'MuckiLogPlugin.config.maxfilesize';
    const CONFIG_PATH_CONVERSIONPATTERN = 'MuckiLogPlugin.config.logpattern';
    
    //Default values
    const CONFIG_PATH_LOG_LEVEL_DEFAULT = 'info';
    const CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT = '10';
    const CONFIG_PATH_MAX_FILESIZE_DEFAULT = '10MB';
    const CONFIG_PATH_CONVERSIONPATTERN_DEFAULT = '%date{Y-m-d H:i:s,u} [%t] %-5p: %m%n%ex';
    
    const PLUGIN_ROOT_PATH = '/custom/plugins/MuckiLogPlugin/src';
    
    /**
     * Absolute path to folder of log config files
     * @var string
     */
    protected string $logConfigPath;

    protected SystemConfigService $config;

    protected KernelInterface $kernel;

    public function __construct(
        SystemConfigService $config,
        KernelInterface $kernel
    )
    {
        $this->config = $config;
        $this->kernel = $kernel;

        $this->logConfigPath = false;
    }
    
    public function isEnabled(): bool
    {
        return $this->config->getBool($this::CONFIG_PATH_ACTIVE);
    }

    public function getPluginConfig()
    {
        return $this->config;
    }

    public function getLogPath(): string
    {
        return $this->kernel->getProjectDir().$this::LOG_PATH;
    }

    public function getLoggerPath(): string
    {
        return dirname(__DIR__).$this::LOGGER_PATH;
    }

    public function getPluginInstallPath(): string
    {
        return dirname(__DIR__);
    }
    
    public function getLogConfigPath(): string
    {
        if(!$this->logConfigPath) {
            $this->logConfigPath = dirname(__DIR__).$this::LOGGER_CONFIG_PATH;
        }

        return $this->logConfigPath;
    }
    
    public function getConfigPath($loggerContext = '', $extensionContext = ''): string
    {
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

        if($this->config->get($this::CONFIG_PATH_MAX_BACKUP_INDEX) != '') {
            return $this->config->get($this::CONFIG_PATH_MAX_BACKUP_INDEX);
        } else {
            return $this::CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT;
        }
    }
    
    public function getMaxFileSize(): string {
        
        if($this->config->get($this::CONFIG_PATH_MAX_FILESIZE) != '') {
            return $this->config->get($this::CONFIG_PATH_MAX_FILESIZE).'MB';
        } else {
            return $this::CONFIG_PATH_MAX_FILESIZE_DEFAULT;
        }
    }
    
    public function getLoglevel(): string {
        
        if($this->config->get($this::CONFIG_PATH_LOG_LEVEL) != '') {
            return $this->config->get($this::CONFIG_PATH_LOG_LEVEL);
        } else {
            return $this::CONFIG_PATH_LOG_LEVEL_DEFAULT;
        }
    }

    public function getConversionPattern(): string
    {
        if($this->config->get($this::CONFIG_PATH_CONVERSIONPATTERN) != '') {
            return $this->config->get($this::CONFIG_PATH_CONVERSIONPATTERN);
        } else {
            return $this::CONFIG_PATH_CONVERSIONPATTERN_DEFAULT;
        }
    }
    
    public function getLoggerFileName($loggerContext = '', $extensionContext = ''): string
    {
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

    public function isDebugNotification(): bool
    {
        return true;
    }
}

