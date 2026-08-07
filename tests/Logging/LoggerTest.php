<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2026 by muckiware
 *
 */
namespace MuckiLogPlugin\Logging;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;

use MuckiLogPlugin\Entity\LoggerSetup;
use MuckiLogPlugin\Logging\Logger;
use MuckiLogPlugin\Core\LogLevel;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Services\SettingsInterface;
use MuckiLogPlugin\Services\LoggingEvent;
use MuckiLogPlugin\Services\Logconfig;
use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\tests\TestCaseBase\Defaults as TestDefaults;

class LoggerTest extends TestCase
{
    public function testExecuteLoggingByLogLevel(): void
    {
        $pluginSettings = $this->createMock(PluginSettings::class);
        $pluginSettings->method('getConfigPath')->willReturn(TestDefaults::getLoggerConfigPath());
        $pluginSettings->method('getLoggerFileName')->willReturn(TestDefaults::getLoggerFilePath());
        $pluginSettings->method('getMaxBackupIndex')->willReturn(PluginSettings::CONFIG_PATH_MAX_BACKUP_INDEX_DEFAULT);
        $pluginSettings->method('getMaxFileSize')->willReturn(PluginSettings::CONFIG_PATH_MAX_FILESIZE_DEFAULT);
        $pluginSettings->method('isEnabledCompress')->willReturn(false);
        $pluginSettings->method('getConversionPattern')->willReturn(PluginSettings::CONFIG_PATH_CONVERSIONPATTERN_DEFAULT);
        $pluginSettings->method('getLoglevel')->willReturn('debug');
        $logconfig = new Logconfig(
            $this->createMock(KernelInterface::class),
            $pluginSettings
        );

        //Create the log config for the test
        $checkConfigPath = $logconfig->checkConfigPath(TestDefaults::DEFAULT_TEST_PLUGIN, TestDefaults::DEFAULT_TEST_VENDOR);
        $this->assertIsBool($checkConfigPath);
        $this->assertTrue($checkConfigPath, 'Logconfig path could not be created for the test.');

        \MuckiLogPlugin\Log4php\Logger::configure(TestDefaults::getLoggerConfigPath());

        $logconfigInterface = $this->createMock(LogconfigInterface::class);
        $Log4phpLogger = \MuckiLogPlugin\Log4php\Logger::getLogger('muckilog');
        $logconfigInterface->method('getLogger')->willReturn($Log4phpLogger);

        $logger = new Logger(
            $this->createMock(EventDispatcherInterface::class),
            $logconfigInterface,
            $this->createMock(SettingsInterface::class),
            $this->createMock(LoggingEvent::class)
        );

        $loggerSetup = new LoggerSetup();
        $loggerSetup->setLogLevel(LogLevel::INFO);
        $loggerSetup->setMessage('This is a test log message.');
        $loggerSetup->setPlugin(TestDefaults::DEFAULT_TEST_PLUGIN);
        $loggerSetup->setVendor(TestDefaults::DEFAULT_TEST_VENDOR);
        $loggerSetup->setPathLogFile(TestDefaults::getLoggerConfigPath());

        $logger->executeLoggingByLogLevel($loggerSetup);

        $checker = true;
    }
}
