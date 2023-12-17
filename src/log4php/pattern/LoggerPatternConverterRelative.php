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
/**
 * Returns the number of milliseconds elapsed since the start of the 
 * application until the creation of the logging event.
 * 
 * @package log4php
 * @subpackage pattern
 * @version $Revision: 1379731 $
 * @since 2.3
 */
class LoggerPatternConverterRelative extends LoggerPatternConverter {

	public function convert(LoggerLoggingEvent $event) {
		$ts = $event->getRelativeTime();
		return number_format($ts, 4);
	}
}
