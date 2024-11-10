<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 *
 *	   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package log4php
 */

/**
 * LoggerPatternConverter is an abstract class that provides the formatting 
 * functionality that derived classes need.
 * 
 * <p>Conversion specifiers in a conversion patterns are parsed to
 * individual PatternConverters. Each of which is responsible for
 * converting a logging event in a converter specific manner.</p>
 * 
 * @version $Revision: 1326626 $
 * @package log4php
 * @subpackage helpers
 * @since 0.3
 *
 * changed by muckiware (c)2024
 */

namespace MuckiLogPlugin\Log4php\pattern;

use MuckiLogPlugin\Log4php\helpers\LoggerFormattingInfo;
use MuckiLogPlugin\Log4php\LoggerLoggingEvent;

abstract class LoggerPatternConverter {
	
	/**
	 * Next converter in the converter chain.
	 * @var LoggerPatternConverter|null
	 */
	public ?LoggerPatternConverter $next = null;

    /**
     * Constructor
     * @param LoggerFormattingInfo|null $formattingInfo
     * @param string|null $option
     */
	public function __construct(
        protected ?LoggerFormattingInfo $formattingInfo=null,
        protected ?string $option=null
    )
    {
		$this->activateOptions();
	}
	
	/**
	 * Called in constructor. Converters which need to process the options 
	 * can override this method. 
	 */
	public function activateOptions(): void { }
  
	/**
	 * Converts the logging event to the desired format. Derived pattern 
	 * converters must implement this method.
	 *
	 * @param LoggerLoggingEvent $event
	 */
	abstract public function convert(LoggerLoggingEvent $event): mixed;

	/**
	 * Converts the event and formats it according to setting in the 
	 * Formatting information object.
	 *
	 * @param string &$sbuf string buffer to write to
	 * @param LoggerLoggingEvent $event Event to be formatted.
	 */
	public function format(string &$sbuf, LoggerLoggingEvent $event): void
    {
		$string = $this->convert($event);
		
		if (!isset($this->formattingInfo)) {
			$sbuf .= $string;
			return;
		}
		
		$fi = $this->formattingInfo;
		
		// Empty string
		if($string === '' || is_null($string)) {
			if($fi->min > 0) {
				$sbuf .= str_repeat(' ', $fi->min);
			}
			return;
		}
		
		$len = strlen($string);
	
		// Trim the string if needed
		if($len > $fi->max) {
			if ($fi->trimLeft) {
				$sbuf .= substr($string, $len - $fi->max, $fi->max);
			} else {
				$sbuf .= substr($string , 0, $fi->max);
			}
		}
		
		// Add padding if needed
		elseif($len < $fi->min) {
			if($fi->padLeft) {
				$sbuf .= str_repeat(' ', $fi->min - $len);
				$sbuf .= $string;
			} else {
				$sbuf .= $string;
				$sbuf .= str_repeat(' ', $fi->min - $len);
			}
		}
		
		// No action needed
		else {
			$sbuf .= $string;
		}
	}
}
