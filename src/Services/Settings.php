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

use MuckiLogPlugin\Core\LogLevel;

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

    const CONFIG_PATH_NOTIFICATION_MAIL_ADDRESS = 'MuckiLogPlugin.config.notificationMailAddress';
    const CONFIG_PATH_NOTIFICATION_MAIL_TEMPLATE_ID = 'MuckiLogPlugin.config.notificationMailTemplateId';
    const CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG = 'MuckiLogPlugin.config.notificationMailActiveDebug';
    const CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO = 'MuckiLogPlugin.config.notificationMailActiveInfo';
    const CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING = 'MuckiLogPlugin.config.notificationMailActiveWarning';
    const CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR = 'MuckiLogPlugin.config.notificationMailActiveError';
    const CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL = 'MuckiLogPlugin.config.notificationMailActiveCritical';

    const CONFIG_PATH_CORE_MAILER_SENDER = 'core.mailerSettings.senderAddress';
    const CONFIG_PATH_CORE_BASIC_EMAIL = 'core.basicInformation.email';

    const CONFIG_PATH_SALES_CHANNEL_ID = 'MuckiLogPlugin.config.salesChannelId';
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

    public function getNotificationMailAddress(): ?string
    {
        if($this->config->getString($this::CONFIG_PATH_NOTIFICATION_MAIL_ADDRESS) != '') {
            return $this->config->getString($this::CONFIG_PATH_NOTIFICATION_MAIL_ADDRESS);
        }

        return null;
    }

    public function getNotificationMailSender(): ?string
    {
        if($this->config->getString($this::CONFIG_PATH_CORE_MAILER_SENDER) != '') {
            return $this->config->getString($this::CONFIG_PATH_CORE_MAILER_SENDER);
        } else {
            if($this->config->getString($this::CONFIG_PATH_CORE_BASIC_EMAIL) != '') {
                return $this->config->getString($this::CONFIG_PATH_CORE_BASIC_EMAIL);
            }
        }

        return null;
    }

    public function getNotificationMailTemplateId(): ?string
    {
        if($this->config->getString($this::CONFIG_PATH_NOTIFICATION_MAIL_TEMPLATE_ID) != '') {
            return $this->config->getString($this::CONFIG_PATH_NOTIFICATION_MAIL_TEMPLATE_ID);
        }

        return null;
    }

    public function isDebugNotification(): bool
    {
        return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG);
    }

    public function isInfoNotification(): bool
    {
        return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO);
    }

    public function isWarningNotification(): bool
    {
        return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING);
    }

    public function isErrorNotification(): bool
    {
        return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR);
    }

    public function isCriticalNotification(): bool
    {
        return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL);
    }

    public function needNotificationByLogLevel(LogLevel $logLevel): bool
    {
        switch ($logLevel) {

            case LogLevel::DEBUG:
                return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG);
            case LogLevel::INFO:
                return$this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO);
            case LogLevel::WARNING:
                return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING);
            case LogLevel::ERROR:
                return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR);
            case LogLevel::CRITICAL:
                return $this->config->getBool($this::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL);
        }

        return false;
    }

    public function getSalesChannelId(): ?string
    {
        if($this->config->getString($this::CONFIG_PATH_SALES_CHANNEL_ID) != '') {
            return $this->config->getString($this::CONFIG_PATH_SALES_CHANNEL_ID);
        }

        return null;
    }
}

