<?php
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021 by muckiware
 *
 */

namespace MuckiLogPlugin\Logging;

use MuckiLogPlugin\Services\SettingsInterface;
use MuckiLogPlugin\Services\LogconfigInterface;

/**
 * @package MuckiLogPlugin\Logging
 *
 */
class Logger implements \MuckiLogPlugin\Logging\LoggerInterface {
    
    /**
     * @var \MuckiLogPlugin\log4php\Logger
     */
	
	protected $_logger;
	
	/**
	 * 
	 * @var SettingsInterface
	 */
	protected $_settings;
	
	/**
	 * 
	 * @var LogconfigInterface
	 */
 	protected $_logconfig;

	
	public function __construct(
 	    LogconfigInterface $logconfig,
	    SettingsInterface $settings
	) {

 	    $this->_logconfig = $logconfig;
	    $this->_settings = $settings;

	    $this->_logger = $this->_logconfig->getLogger();
	}
	
	/**
	 * Log into debugging level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function debugItem($message, $loggerContext = '', $extensionContext = '') {

	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
			    $this->_logger->debug($message);
			}
		}
	}
	
	/**
	 * Log into fatal level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function fatalItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->fatal($message);
			}
		}
	}
	
	/**
	 * Log into trace level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function traceItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->trace($message);
			}
		}
	}
	
	/**
	 * Log into info level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function infoItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->info($message);
			}
		}
	}
	
	/**
	 * Log into error level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function errorItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->error($message);
			}
		}
	}
	
	/**
	 * Log into warning level
	 *
	 * @param string $message
	 * @param string $loggerContext, usualy name of plugin
	 * @param string $extensionContext, like name of plugin vendor
	 *
	 * @return void
	 */
	public function warnItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->warn($message);
			}
		}
	}
	
	/**
	 * Method for to load a xml config file, if it already exists.
	 *
	 * @param string $loggerContext
	 * @return boolean
	 */
	protected function _setLoggerConfig($loggerContext = '', $extensionContext = ''): bool {
		
		if($this->_logconfig->checkConfigPath($loggerContext, $extensionContext)) {

		    $this->_logger->configure($this->_settings->getConfigPath($loggerContext, $extensionContext));
		    return true;
		} else {
		    return false;
		}
	}
}