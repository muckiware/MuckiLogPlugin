<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package log4php
 */
namespace MuckiLogPlugin\log4php\layouts;

use MuckiLogPlugin\log4php\pattern\LoggerPatternConverter;
use MuckiLogPlugin\log4php\LoggerLayout;
use MuckiLogPlugin\log4php\LoggerException;
use MuckiLogPlugin\log4php\LoggerLoggingEvent;
use MuckiLogPlugin\log4php\helpers\LoggerPatternParser;

/**
 * A flexible layout configurable with a pattern string.
 * 
 * Configurable parameters:
 * 
 * * converionPattern - A string which controls the formatting of logging 
 *   events. See docs for full specification.
 * 
 * @package log4php
 * @subpackage layouts
 * @version $Revision: 1395470 $
 */
class LoggerLayoutPattern extends LoggerLayout {
	
	/** Default conversion pattern */
	const DEFAULT_CONVERSION_PATTERN = '%date %-5level %logger %message%newline';

	/** Default conversion TTCC Pattern */
	const TTCC_CONVERSION_PATTERN = '%d [%t] %p %c %x - %m%n';

	/** The conversion pattern. */ 
	protected $pattern = self::DEFAULT_CONVERSION_PATTERN;
	
	/** Maps conversion keywords to the relevant converter (default implementation). */
	protected static $defaultConverterMap = array(
		'c' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLogger',
		'lo' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLogger',
		'logger' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLogger',
		
		'C' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterClass',
		'class' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterClass',
		
		'cookie' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterCookie',
		
		'd' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterDate',
		'date' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterDate',
		
		'e' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterEnvironment',
		'env' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterEnvironment',
		
		'ex' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterThrowable',
		'exception' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterThrowable',
		'throwable' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterThrowable',
		
		'F' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterFile',
		'file' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterFile',
			
		'l' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLocation',
		'location' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLocationn',
		
		'L' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLine',
		'line' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLine',
		
		'm' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMessage',
		'msg' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMessage',
		'message' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMessage',
		
		'M' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMethod',
		'method' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMethod',
		
		'n' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterNewLine',
		'newline' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterNewLine',
		
		'p' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLevel',
		'le' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLevel',
		'level' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLevel',
	
		'r' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterRelative',
		'relative' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterRelative',
		
		'req' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterRequest',
		'request' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterRequest',
		
		's' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterServer',
		'server' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterServer',
		
		'ses' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSession',
		'session' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSession',
		
		'sid' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSessionID',
		'sessionid' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSessionID',
	
		't' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterProcess',
		'pid' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterProcess',
		'process' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterProcess',
		
		'x' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterNDC',
		'ndc' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterNDC',
			
		'X' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMDC',
		'mdc' => 'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMDC',
	);

	/** Maps conversion keywords to the relevant converter. */
	protected $converterMap = array();
	
	/** 
	 * Head of a chain of Converters.
	 * @var LoggerPatternConverter 
	 */
	private $head;

	/** Returns the default converter map. */
	public static function getDefaultConverterMap() {
		return self::$defaultConverterMap;
	}
	
	/** Constructor. Initializes the converter map. */
	public function __construct() {
		$this->converterMap = self::$defaultConverterMap;
	}
	
	/**
	 * Sets the conversionPattern option. This is the string which
	 * controls formatting and consists of a mix of literal content and
	 * conversion specifiers.
	 * @param array $conversionPattern
	 */
	public function setConversionPattern($conversionPattern) {
		$this->pattern = $conversionPattern;
	}
	
	/**
	 * Processes the conversion pattern and creates a corresponding chain of 
	 * pattern converters which will be used to format logging events. 
	 */
	public function activateOptions() {
		if (!isset($this->pattern)) {
			throw new LoggerException("Mandatory parameter 'conversionPattern' is not set.");
		}
		
		$parser = new LoggerPatternParser($this->pattern, $this->converterMap);
		$this->head = $parser->parse();
	}
	
	/**
	 * Produces a formatted string as specified by the conversion pattern.
	 *
	 * @param LoggerLoggingEvent $event
	 * @return string
	 */
	public function format(LoggerLoggingEvent $event) {
		$sbuf = '';
		$converter = $this->head;
		while ($converter !== null) {
			$converter->format($sbuf, $event);
			$converter = $converter->next;
		}
		return $sbuf;
	}
}