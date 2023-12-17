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

namespace MuckiLogPlugin\Logging;

/**
 * @package MuckiLogPlugin\Logging
 *
 */
interface LoggerInterface {

    /**
     * Log into debugging level
     *
     * @param string $message
     * @param string $loggerContext, usually name of plugin
     * @param string $extensionContext, like name of plugin vendor
     *
     * @return void
     */
	public function debugItem($message, $loggerContext = '', $extensionContext = ''): void;
	
	/**
	 * Log into fatal problems level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function criticalItem($message, $loggerContext = '', $extensionContext = ''): void;
	
	/**
	 * Log into info level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function infoItem($message, $loggerContext = '', $extensionContext = ''): void;
	
	/**
	 * Log in error problems level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function errorItem(string $message, $loggerContext = '', $extensionContext = ''): void;
	
	/**
	 * Log into warning level
	 *
	 * @param string $message
	 * @param string $loggerContext, usually name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function warnItem(string $message, $loggerContext = '', $extensionContext = ''): void;
    public function warningItem($message, $loggerContext = '', $extensionContext = ''): void;
}