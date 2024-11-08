<?php

declare(strict_types=1);

namespace Services;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;

use MuckiLogPlugin\Services\Helper;
use MuckiLogPlugin\Core\ConfigPath;
use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Core\Defaults as PluginDefaults;

class SettingsTest extends TestCase
{
    public function testCheckSettingsIsEnabled(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);

        $settingsClass = new PluginSettings($config, $kernel);
        $config->method('getBool')->willReturn(true);
        $isEnabled1 = $settingsClass->isEnabled();
        static::assertIsBool($isEnabled1, 'isEnabled method should return boolean');
        static::assertTrue($isEnabled1, 'isEnabled method should return true');
    }

    public function testCheckSettingsIsNotEnabled(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);

        $settingsClass = new PluginSettings($config, $kernel);

        $config->method('getBool')->willReturn(false);
        $isEnabled2 = $settingsClass->isEnabled();
        static::assertIsBool($isEnabled2, 'isEnabled method should return boolean');
        static::assertFalse($isEnabled2, 'isEnabled method should return false');
    }

    public function testNeedNotificationByLogLevelDebug(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getBool')
            ->with(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG->value)
            ->willReturn(true);

        $settingsClass = new PluginSettings($config, $kernel);
        $result = $settingsClass->needNotificationByLogLevel(LogLevel::DEBUG);
        static::assertTrue($result, 'needNotificationByLogLevel should return true for DEBUG level');
    }

    public function testNeedNotificationByLogLevelInfo(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getBool')
            ->with(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO->value)
            ->willReturn(true);

        $settingsClass = new PluginSettings($config, $kernel);
        $result = $settingsClass->needNotificationByLogLevel(LogLevel::INFO);
        static::assertTrue($result, 'needNotificationByLogLevel should return true for INFO level');
    }

    public function testNeedNotificationByLogLevelWarning(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getBool')
            ->with(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING->value)
            ->willReturn(true);

        $settingsClass = new PluginSettings($config, $kernel);
        $result = $settingsClass->needNotificationByLogLevel(LogLevel::WARNING);
        static::assertTrue($result, 'needNotificationByLogLevel should return true for WARNING level');
    }

    public function testNeedNotificationByLogLevelError(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getBool')
            ->with(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR->value)
            ->willReturn(true);

        $settingsClass = new PluginSettings($config, $kernel);
        $result = $settingsClass->needNotificationByLogLevel(LogLevel::ERROR);
        static::assertTrue($result, 'needNotificationByLogLevel should return true for ERROR level');
    }

    public function testNeedNotificationByLogLevelCritical(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $config->method('getBool')
            ->with(ConfigPath::CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL->value)
            ->willReturn(true);

        $settingsClass = new PluginSettings($config, $kernel);
        $result = $settingsClass->needNotificationByLogLevel(LogLevel::CRITICAL);
        static::assertTrue($result, 'needNotificationByLogLevel should return true for CRITICAL level');
    }

    public function testGetLoggerFileNameWithLoggerAndExtensionContext(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $kernel->method('getLogDir')->willReturn('/var/log');

        $settingsClass = new PluginSettings($config, $kernel);
        $loggerFileName = $settingsClass->getLoggerFileName('acme', 'dev');
        static::assertEquals(
            '/var/log/dev.acme.log',
            $loggerFileName,
            'getLoggerFileName should return the correct file name with logger and extension context'
        );
    }

    public function testGetLoggerFileNameWithLoggerContextOnly(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $kernel->method('getLogDir')->willReturn(PluginDefaults::LOG_PATH);

        $settingsClass = new PluginSettings($config, $kernel);
        $loggerFileName = $settingsClass->getLoggerFileName(PluginDefaults::CONTEXT);
        static::assertEquals(
            '/var/log/muckilog.acme.log',
            $loggerFileName,
            'getLoggerFileName should return the correct file name with logger context only'
        );
    }

    public function testGetLoggerFileNameWithExtensionContextOnly(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $kernel->method('getLogDir')->willReturn(PluginDefaults::LOG_PATH);

        $settingsClass = new PluginSettings($config, $kernel);
        $loggerFileName = $settingsClass->getLoggerFileName('', 'dev');
        static::assertEquals(
            '/var/log/dev.log',
            $loggerFileName,
            'getLoggerFileName should return the correct file name with extension context only'
        );
    }

    public function testGetLoggerFileNameWithNoContext(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);
        $kernel->method('getLogDir')->willReturn(PluginDefaults::LOG_PATH);

        $settingsClass = new PluginSettings($config, $kernel);
        $loggerFileName = $settingsClass->getLoggerFileName();
        static::assertEquals(
            '/var/log/muckilog.log',
            $loggerFileName,
            'getLoggerFileName should return the correct file name with no context'
        );
    }

    public function testGetConfigPathWithLoggerAndExtensionContext(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);

        $settingsClass = new PluginSettings($config, $kernel);
        $configPath = $settingsClass->getConfigPath(
            PluginDefaults::CONTEXT,
            PluginDefaults::DEFAULT_SW_EXTENSION
        );
        static::assertEquals(
            $settingsClass->getLogConfigPath().'/logconfig.dev.acme.xml',
            $configPath,
            'getConfigPath should return the correct config path with logger and extension context'
        );
    }

    public function testGetConfigPathWithLoggerContextOnly(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);

        $settingsClass = new PluginSettings($config, $kernel);
        $configPath = $settingsClass->getConfigPath('acme');
        static::assertEquals(
            $settingsClass->getLogConfigPath().'/logconfig.acme.xml',
            $configPath,
            'getConfigPath should return the correct config path with logger context only'
        );
    }

    public function testGetConfigPathWithExtensionContextOnly(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);

        $settingsClass = new PluginSettings($config, $kernel);
        $configPath = $settingsClass->getConfigPath('', 'dev');
        static::assertEquals(
            $settingsClass->getLogConfigPath().'/logconfig.dev.xml',
            $configPath,
            'getConfigPath should return the correct config path with extension context only'
        );
    }

    public function testGetConfigPathWithNoContext(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $config = $this->createMock(SystemConfigService::class);

        $settingsClass = new PluginSettings($config, $kernel);
        $configPath = $settingsClass->getConfigPath();
        static::assertEquals(
            $settingsClass->getLogConfigPath().'/logconfig.xml',
            $configPath,
            'getConfigPath should return the correct config path with no context'
        );
    }
}
