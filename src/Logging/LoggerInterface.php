<?php
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 *
 * @author     Muckiware
 *
 */

namespace MuckiLogPlugin\Logging;

/**
 * @package MuckiLogPlugin\Logging
 *
 */
interface LoggerInterface
{
    /**
     * Log into debugging level
     *
     * @param string $message
     * @param string $loggerContext, usually name of plugin
     * @param string $extensionContext, like name of plugin vendor
     *
     * @return void
     */
	public function debugItem(mixed $message, string $loggerContext = '', string $extensionContext = ''): void;
	
	/**
	 * Log into fatal problems level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function criticalItem(mixed $message, string $loggerContext = '', string $extensionContext = ''): void;
	
	/**
	 * Log into info level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function infoItem(mixed $message, string $loggerContext = '', string $extensionContext = ''): void;
	
	/**
	 * Log in error problems level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function errorItem(mixed $message, string $loggerContext = '', string $extensionContext = ''): void;
	
	/**
	 * Log into warning level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function warnItem(mixed $message, string $loggerContext = '', string $extensionContext = ''): void;
    public function warningItem(mixed $message, string $loggerContext = '', string $extensionContext = ''): void;
}