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

use MuckiLogPlugin\Core\Defaults as PluginDefaults;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Core\ConfigPath;

class Settings implements SettingsInterface
{
    const MAXBACKUPINDEX = 10;
    //Default values
    const CONFIG_PATH_LOG_LEVEL_DEFAULT = 'info';
    const CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT = '10';
    const CONFIG_PATH_MAX_FILESIZE_DEFAULT = '10MB';
    const CONFIG_PATH_CONVERSIONPATTERN_DEFAULT = '%date{Y-m-d H:i:s,u} [%t] %-5p: %m%n%ex';
    /**
     * Absolute path to folder of log config files
     * @var string|null
     */
    protected ?string $logConfigPath;

    protected SystemConfigService $config;

    protected KernelInterface $kernel;

    public function __construct(
        SystemConfigService $config,
        KernelInterface $kernel
    )
    {
        $this->config = $config;
        $this->kernel = $kernel;

        $this->logConfigPath = null;
    }
    
    public function isEnabled(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_ACTIVE->value);
    }

    public function getPluginConfig(): SystemConfigService
    {
        return $this->config;
    }

    public function getLogPath(): string
    {
        return $this->kernel->getProjectDir().PluginDefaults::LOG_PATH;
    }

    public function getLoggerPath(): string
    {
        return dirname(__DIR__).PluginDefaults::LOGGER_PATH;
    }

    public function getPluginInstallPath(): string
    {
        return dirname(__DIR__);
    }
    
    public function getLogConfigPath(): string
    {
        if(!$this->logConfigPath) {
            $this->logConfigPath = dirname(__DIR__).PluginDefaults::LOGGER_CONFIG_PATH;
        }

        return $this->logConfigPath;
    }
    
    public function getConfigPath(string $loggerContext='', string $extensionContext=''): string
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

        if($this->config->getString(ConfigPath::CONFIG_PATH_MAX_BACKUP_INDEX->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_MAX_BACKUP_INDEX->value);
        } else {
            return $this::CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT;
        }
    }
    
    public function getMaxFileSize(): string {
        
        if($this->config->getString(ConfigPath::CONFIG_PATH_MAX_FILESIZE->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_MAX_FILESIZE->value).'MB';
        } else {
            return $this::CONFIG_PATH_MAX_FILESIZE_DEFAULT;
        }
    }
    
    public function getLoglevel(): string {
        
        if($this->config->getString(ConfigPath::CONFIG_PATH_LOG_LEVEL->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_LOG_LEVEL->value);
        } else {
            return $this::CONFIG_PATH_LOG_LEVEL_DEFAULT;
        }
    }

    public function getConversionPattern(): string
    {
        if($this->config->getString(ConfigPath::CONFIG_PATH_CONVERSIONPATTERN->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_CONVERSIONPATTERN->value);
        } else {
            return $this::CONFIG_PATH_CONVERSIONPATTERN_DEFAULT;
        }
    }
    
    public function getLoggerFileName(string $loggerContext='', string $extensionContext=''): string
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
        if($this->config->getString(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ADDRESS->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ADDRESS->value);
        }

        return null;
    }

    public function getNotificationMailSender(): ?string
    {
        if($this->config->getString(ConfigPath::CONFIG_PATH_CORE_MAILER_SENDER->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_CORE_MAILER_SENDER->value);
        } else {
            if($this->config->getString(ConfigPath::CONFIG_PATH_CORE_BASIC_EMAIL->value) != '') {
                return $this->config->getString(ConfigPath::CONFIG_PATH_CORE_BASIC_EMAIL->value);
            }
        }

        return null;
    }

    public function getNotificationMailTemplateId(): ?string
    {
        if($this->config->getString(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_TEMPLATE_ID->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_TEMPLATE_ID->value);
        }

        return null;
    }

    public function isDebugNotification(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG->value);
    }

    public function isInfoNotification(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO->value);
    }

    public function isWarningNotification(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING->value);
    }

    public function isErrorNotification(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR->value);
    }

    public function isCriticalNotification(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL->value);
    }

    public function needNotificationByLogLevel(LogLevel $logLevel): bool
    {
        switch ($logLevel) {

            case LogLevel::DEBUG:
                $needNotificationByLogLevel = $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG->value);
                break;
            case LogLevel::INFO:
                $needNotificationByLogLevel = $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO->value);
                break;
            case LogLevel::WARNING:
                $needNotificationByLogLevel = $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING->value);
                break;
            case LogLevel::ERROR:
                $needNotificationByLogLevel = $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR->value);
                break;
            case LogLevel::CRITICAL:
                $needNotificationByLogLevel = $this->config->getBool(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL->value);
                break;
            default:
                $needNotificationByLogLevel = false;
                break;
        }

        return $needNotificationByLogLevel;
    }

    public function getSendMailMode(): string
    {
        if($this->config->getString(ConfigPath::CONFIG_PATH_SEND_MAIL_MODE->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_SEND_MAIL_MODE->value);
        }

        return PluginDefaults::DEFAULT_SEND_MAIL_MODE;
    }

    public function getSalesChannelId(): ?string
    {
        if($this->config->getString(ConfigPath::CONFIG_PATH_SALES_CHANNEL_ID->value) != '') {
            return $this->config->getString(ConfigPath::CONFIG_PATH_SALES_CHANNEL_ID->value);
        }

        return null;
    }

    public function isNotificationMailEnabled(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_ACTIVE_NOTIFICATION_MAIL->value);
    }
}
