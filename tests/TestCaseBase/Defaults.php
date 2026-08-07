<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2026 by muckiware
 *
 */
namespace MuckiLogPlugin\tests\TestCaseBase;
/**
 * Plugin wide default values
 */
final class Defaults
{
    public const DEFAULT_TEST_VENDOR = 'muwa';
    public const DEFAULT_TEST_PLUGIN = 'log-plugin';
    public const DEFAULT_PLUGIN_CONFIG_PATH = 'custom/plugins/MuckiLogPlugin';

    public static function getPluginPath(): string
    {
        return str_replace('/tests','', dirname(__DIR__));
    }

    public static function getLoggerConfigPath(): string
    {
        return self::getPluginPath().'/src/Resources/config/logconfig.'.self::DEFAULT_TEST_PLUGIN.'.'.self::DEFAULT_TEST_VENDOR.'.xml';
    }

    public static function getTestVarFolder(): string
    {
        return dirname(__DIR__).'/var';
    }

    public static function getLoggerFilePath(): string
    {
        return self::getTestVarFolder().'/'.self::DEFAULT_TEST_PLUGIN.'.'.self::DEFAULT_TEST_VENDOR.'.log';
    }
}
