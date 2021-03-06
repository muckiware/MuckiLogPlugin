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
     * @param string $loggerContext, usualy name of plugin
     * @param string $extensionContext, like name of plugin vendor
     * 
     * @return void
     */
	public function debugItem($message, $loggerContext = '', $extensionContext = '');
	
	/**
	 * Log into fatal problems level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function fatalItem($message, $loggerContext = '', $extensionContext = '');
	
	/**
	 * Log into trace level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function traceItem($message, $loggerContext = '', $extensionContext = '');
	
	/**
	 * Log into info level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function infoItem($message, $loggerContext = '', $extensionContext = '');
	
	/**
	 * Log in error problems level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function errorItem($message, $loggerContext = '', $extensionContext = '');
	
	/**
	 * Log into warning level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function warnItem($message, $loggerContext = '', $extensionContext = '');

}