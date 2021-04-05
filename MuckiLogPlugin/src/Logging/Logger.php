<?php
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Smoppit
 * @package    SmoppitLogger
 * @copyright  Copyright (c) 2021 by smoppit.com
 *
 * @author      smoppit.com
 *
 */

namespace MuckiLogPlugin\Logging;

use MuckiLogPlugin\Services\Settings;
use MuckiLogPlugin\Services\Logconfig;

/**
 * @package MuckiLogPlugin\Logging
 *
 */
class Logger {
    
    /**
     * @var \MuckiLogPlugin\log4php\Logger
     */
	
	protected $_logger;
	
	/**
	 * 
	 * @var Settings
	 */
	protected $_settings;
	
	/**
	 * 
	 * @var Logconfig
	 */
 	protected $_logconfig;

	
	public function __construct(
 	    Logconfig $logconfig,
	    Settings $settings
	) {

 	    $this->_logconfig = $logconfig;
	    $this->_settings = $settings;

	    $this->_logger = $this->_logconfig->getLogger();
	}
	
	public function debugItem($message, $loggerContext = '', $extensionContext = '') {

	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->debug($message);
			}
		} else {
			return null;
		}
	}
	
	public function fatalItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->fatal($message);
			}
		} else {
			return null;
		}
	}
	
	public function traceItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->trace($message);
			}
		} else {
			return null;
		}
	}
	
	public function infoItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->info($message);
			}
		} else {
			return null;
		}
	}
	
	public function errorItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->error($message);
			}
		} else {
			return null;
		}
	}
	
	public function warnItem($message, $loggerContext = '', $extensionContext = '') {
		
	    if($this->_settings->isEnabled()) {
	        if($this->_setLoggerConfig($loggerContext, $extensionContext)) {
				$this->_logger->warn($message);
			}
		} else {
			return null;
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