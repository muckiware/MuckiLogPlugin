<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   tests
 * @package    log4php
 * @subpackage appenders
 * @license    http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @version    $Revision$
 * @link       http://logging.apache.org/log4php
 *
 * changed by muckiware (c)2024
 * @link https://github.com/muckiware/MuckiLogPlugin
 */
namespace MuckiLogPlugin\tests\Log4php;

use MuckiLogPlugin\Log4php\Logger;
use MuckiLogPlugin\Log4php\LoggerLevel;
use MuckiLogPlugin\Log4php\LoggerLoggingEvent;

/** A set of helper functions for running tests. */
class LoggerTestHelper
{
    /**
     * Returns a test logging event with level set to TRACE.
     * @param string $message
     * @param string $logger
     * @return LoggerLoggingEvent
     */
	public static function getTraceEvent(string $message = 'test', string $logger = "test"): LoggerLoggingEvent
    {
		return new LoggerLoggingEvent(__CLASS__, new Logger($logger), LoggerLevel::getLevelTrace(), $message);
	}

    /**
     * Returns a test logging event with level set to DEBUG.
     * @param string $message
     * @param string $logger
     * @return LoggerLoggingEvent
     */
	public static function getDebugEvent(string $message = 'test', string $logger = "test"): LoggerLoggingEvent
    {
		return new LoggerLoggingEvent(__CLASS__, new Logger($logger), LoggerLevel::getLevelDebug(), $message);
	}

    /**
     * Returns a test logging event with level set to INFO.
     * @param string $message
     * @param string $logger
     * @return LoggerLoggingEvent
     */
	public static function getInfoEvent(string $message = 'test', string $logger = "test"): LoggerLoggingEvent
    {
		return new LoggerLoggingEvent(__CLASS__, new Logger($logger), LoggerLevel::getLevelInfo(), $message);
	}

    /**
     * Returns a test logging event with level set to WARNING.
     * @param string $message
     * @param string $logger
     * @return LoggerLoggingEvent
     */
	public static function getWarningEvent(string $message = 'test', string $logger = "test"): LoggerLoggingEvent
    {
		return new LoggerLoggingEvent(__CLASS__, new Logger($logger), LoggerLevel::getLevelWarning(), $message);
	}

    /**
     * Returns a test logging event with level set to ERROR.
     * @param string $message
     * @param string $logger
     * @return LoggerLoggingEvent
     */
	public static function getErrorEvent(string $message = 'test', string $logger = "test"): LoggerLoggingEvent
    {
		return new LoggerLoggingEvent(__CLASS__, new Logger($logger), LoggerLevel::getLevelError(), $message);
	}

    /**
     * Returns a test logging event with level set to CRITICAL.
     * @param string $message
     * @param string $logger
     * @return LoggerLoggingEvent
     */
	public static function getCriticalEvent(string $message = 'test', string $logger = "test"): LoggerLoggingEvent
    {
		return new LoggerLoggingEvent(__CLASS__, new Logger($logger), LoggerLevel::getLevelCritical(), $message);
	}
	
	/**
	 * Returns an array of logging events, one for each level, sorted ascending
	 * by severitiy.
	 */
	public static function getAllEvents(string $message = 'test'): array
    {
		return array(
			self::getTraceEvent($message),
			self::getDebugEvent($message),
			self::getInfoEvent($message),
			self::getWarningEvent($message),
			self::getErrorEvent($message),
			self::getCriticalEvent($message),
		);
	}
	
	/** Returns an array of all existing levels, sorted ascending by severity. */
	public static function getAllLevels(): array
    {
		return array(
			LoggerLevel::getLevelTrace(),
			LoggerLevel::getLevelDebug(),
			LoggerLevel::getLevelInfo(),
			LoggerLevel::getLevelWarning(),
			LoggerLevel::getLevelError(),
			LoggerLevel::getLevelCritical(),
		);
	}
	
	/** Returns a simple configuration with one echo appender tied to root logger. */
	public static function getEchoConfig(): array
    {
		return array(
	        'threshold' => 'ALL',
	        'rootLogger' => array(
	            'level' => 'trace',
	            'appenders' => array('default'),
			),
	        'appenders' => array(
	            'default' => array(
	                'class' => 'LoggerAppenderEcho',
	                'layout' => array(
	                    'class' => 'LoggerLayoutSimple',
					),
				),
			),
		);
	}
	
	/** Returns a simple configuration with one echo appender using the pattern layout. */
	public static function getEchoPatternConfig($pattern): array
    {
		return array(
			'threshold' => 'ALL',
			'rootLogger' => array(
				'level' => 'trace',
				'appenders' => array('default'),
			),
			'appenders' => array(
				'default' => array(
					'class' => 'LoggerAppenderEcho',
					'layout' => array(
						'class' => 'LoggerLayoutPattern',
						'params' => array(
							'conversionPattern' => $pattern
						)
					),
				),
			),
		);
	}

    public static function getTestDirPath(): string
    {
        return __DIR__.'/var';
    }
}
