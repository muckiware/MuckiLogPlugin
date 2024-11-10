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
 *
 * changed by muckiware (c)2024
 */
namespace MuckiLogPlugin\Log4php\layouts;

use MuckiLogPlugin\Log4php\pattern\LoggerPatternConverter;
use MuckiLogPlugin\Log4php\LoggerLayout;
use MuckiLogPlugin\Log4php\LoggerException;
use MuckiLogPlugin\Log4php\LoggerLoggingEvent;
use MuckiLogPlugin\Log4php\helpers\LoggerPatternParser;

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
class LoggerLayoutPattern extends LoggerLayout
{
	
	/** Default conversion pattern */
	const DEFAULT_CONVERSION_PATTERN = '%date %-5level %logger %message%newline';

	/** Default conversion TTCC Pattern */
	const TTCC_CONVERSION_PATTERN = '%d [%t] %p %c %x - %m%n';

	/** The conversion pattern. */
	protected mixed $pattern = self::DEFAULT_CONVERSION_PATTERN;
	
	/** Maps conversion keywords to the relevant converter (default implementation). */
	protected static array $defaultConverterMap = array(
		'c' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLogger',
		'lo' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLogger',
		'logger' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLogger',
		
		'C' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterClass',
		'class' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterClass',
		
		'cookie' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterCookie',
		
		'd' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterDate',
		'date' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterDate',
		
		'e' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterEnvironment',
		'env' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterEnvironment',
		
		'ex' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterThrowable',
		'exception' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterThrowable',
		'throwable' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterThrowable',
		
		'F' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterFile',
		'file' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterFile',
			
		'l' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLocation',
		'location' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLocationn',
		
		'L' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLine',
		'line' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLine',
		
		'm' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMessage',
		'msg' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMessage',
		'message' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMessage',
		
		'M' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMethod',
		'method' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMethod',
		
		'n' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterNewLine',
		'newline' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterNewLine',
		
		'p' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLevel',
		'le' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLevel',
		'level' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLevel',
	
		'r' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterRelative',
		'relative' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterRelative',
		
		'req' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterRequest',
		'request' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterRequest',
		
		's' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterServer',
		'server' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterServer',
		
		'ses' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSession',
		'session' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSession',
		
		'sid' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSessionID',
		'sessionid' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSessionID',
	
		't' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterProcess',
		'pid' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterProcess',
		'process' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterProcess',
		
		'x' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterNDC',
		'ndc' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterNDC',
			
		'X' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMDC',
		'mdc' => 'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMDC',
	);

	/** Maps conversion keywords to the relevant converter. */
	protected array $converterMap = array();
	
	/**
	 * Head of a chain of Converters.
	 * @var LoggerPatternConverter|null
	 */
	private ?LoggerPatternConverter $head;

	/** Returns the default converter map. */
	public static function getDefaultConverterMap(): array
    {
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
	public function setConversionPattern(mixed $conversionPattern): void
    {
		$this->pattern = $conversionPattern;
	}
	
	/**
	 * Processes the conversion pattern and creates a corresponding chain of 
	 * pattern converters which will be used to format logging events. 
	 */
	public function activateOptions(): void
    {
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
	public function format(LoggerLoggingEvent $event): string
    {
		$sbuf = '';
		$converter = $this->head;
		while ($converter !== null) {
			$converter->format($sbuf, $event);
			$converter = $converter->next;
		}
		return $sbuf;
	}
}