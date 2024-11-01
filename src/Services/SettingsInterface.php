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

use Shopware\Core\System\SystemConfig\SystemConfigService;

use MuckiLogPlugin\Core\LogLevel;

interface SettingsInterface
{
    public function isEnabled(): bool;

    public function getPluginConfig(): SystemConfigService;

    public function getLogPath(): string;
    
    public function getLogConfigPath(): string;
    
    public function getConfigPath(string $loggerContext='', string $extensionContext=''): string;
    
    public function getMaxBackupIndex(): string;
    
    public function getMaxFileSize(): string;
    
    public function getLoglevel(): string;
    
    public function getLoggerFileName(string $loggerContext='', string $extensionContext=''): string;

    public function getConversionPattern(): string;
    public function getNotificationMailTemplateId(): ?string;
    public function getNotificationMailAddress(): ?string;
    public function isDebugNotification(): bool;
    public function isInfoNotification(): bool;
    public function isWarningNotification(): bool;
    public function isErrorNotification(): bool;
    public function isCriticalNotification(): bool;

    public function needNotificationByLogLevel(LogLevel $logLevel): bool;
    public function getSalesChannelId(): ?string;
}

