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
namespace MuckiLogPlugin\log4php\pattern;

use MuckiLogPlugin\log4php\LoggerLoggingEvent;
use MuckiLogPlugin\log4php\helpers\LoggerUtils;
/**
 * Returns the fully qualified class name of the class from which the logging 
 * request was issued.
 * 
 * @package log4php
 * @subpackage pattern
 * @version $Revision: 1326626 $
 * @since 2.3
 */
class LoggerPatternConverterClass extends LoggerPatternConverter {

	/** Length to which to shorten the class name. */
	private ?int $length = null;
	
	/** Holds processed class names. */
	private array $cache = array();
	
	public function activateOptions() {
		// Parse the option (desired output length)
		if (isset($this->option) && is_numeric($this->option) && $this->option >= 0) {
			$this->length = (integer) $this->option;
		}
	}

	public function convert(LoggerLoggingEvent $event) {
		$name = $event->getLocationInformation()->getClassName();
	
		if (!isset($this->cache[$name])) {
	
			// If length is set return shortened class name
			if (isset($this->length)) {
				$this->cache[$name] = LoggerUtils::shortenClassName($name, $this->length);
			}
				
			// If no length is specified return the full class name
			else {
				$this->cache[$name] = $name;
			}
		}
	
		return $this->cache[$name];
	}
}
 