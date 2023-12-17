<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements. See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License. You may obtain a copy of the License at
 * 
 *		http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * @package log4php
 */
namespace MuckiLogPlugin\log4php;

if (function_exists('__autoload')) {
	trigger_error("log4php: It looks like your code is using an __autoload() function. log4php uses spl_autoload_register() which will bypass your __autoload() function and may break autoloading.", E_USER_WARNING);
}

spl_autoload_register(array('MuckiLogPlugin\log4php\LoggerAutoloader', 'autoload'));

/**
 * Class autoloader.
 * 
 * @package log4php
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @version $Revision: 1394956 $
 */
class LoggerAutoloader {
	
	/** Maps classnames to files containing the class. */
	private static array $classes = array(
	
		// Base
		'MuckiLogPlugin\log4php\LoggerAppender' => '/LoggerAppender.php',
		'MuckiLogPlugin\log4php\LoggerAppenderPool' => '/LoggerAppenderPool.php',
		'MuckiLogPlugin\log4php\LoggerConfigurable' => '/LoggerConfigurable.php',
		'MuckiLogPlugin\log4php\LoggerConfigurator' => '/LoggerConfigurator.php',
		'MuckiLogPlugin\log4php\LoggerException' => '/LoggerException.php',
		'MuckiLogPlugin\log4php\LoggerFilter' => '/LoggerFilter.php',
		'MuckiLogPlugin\log4php\LoggerHierarchy' => '/LoggerHierarchy.php',
		'MuckiLogPlugin\log4php\LoggerLevel' => '/LoggerLevel.php',
		'MuckiLogPlugin\log4php\LoggerLocationInfo' => '/LoggerLocationInfo.php',
		'MuckiLogPlugin\log4php\LoggerLoggingEvent' => '/LoggerLoggingEvent.php',
		'MuckiLogPlugin\log4php\LoggerMDC' => '/LoggerMDC.php',
		'MuckiLogPlugin\log4php\LoggerNDC' => '/LoggerNDC.php',
		'MuckiLogPlugin\log4php\LoggerLayout' => '/LoggerLayout.php',
		'MuckiLogPlugin\log4php\LoggerReflectionUtils' => '/LoggerReflectionUtils.php',
		'MuckiLogPlugin\log4php\LoggerRoot' => '/LoggerRoot.php',
		'MuckiLogPlugin\log4php\LoggerThrowableInformation' => '/LoggerThrowableInformation.php',
		
		// Appenders
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderConsole' => '/appenders/LoggerAppenderConsole.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderDailyFile' => '/appenders/LoggerAppenderDailyFile.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderEcho' => '/appenders/LoggerAppenderEcho.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderFile' => '/appenders/LoggerAppenderFile.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderMail' => '/appenders/LoggerAppenderMail.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderMailEvent' => '/appenders/LoggerAppenderMailEvent.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderMongoDB' => '/appenders/LoggerAppenderMongoDB.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderNull' => '/appenders/LoggerAppenderNull.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderFirePHP' => '/appenders/LoggerAppenderFirePHP.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderPDO' => '/appenders/LoggerAppenderPDO.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderPhp' => '/appenders/LoggerAppenderPhp.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderRollingFile' => '/appenders/LoggerAppenderRollingFile.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderSocket' => '/appenders/LoggerAppenderSocket.php',
		'MuckiLogPlugin\log4php\appenders\LoggerAppenderSyslog' => '/appenders/LoggerAppenderSyslog.php',
		
		// Configurators
		'MuckiLogPlugin\log4php\configurators\LoggerConfigurationAdapter' => '/configurators/LoggerConfigurationAdapter.php',
		'MuckiLogPlugin\log4php\configurators\LoggerConfigurationAdapterINI' => '/configurators/LoggerConfigurationAdapterINI.php',
		'MuckiLogPlugin\log4php\configurators\LoggerConfigurationAdapterPHP' => '/configurators/LoggerConfigurationAdapterPHP.php',
		'MuckiLogPlugin\log4php\configurators\LoggerConfigurationAdapterXML' => '/configurators/LoggerConfigurationAdapterXML.php',
		'MuckiLogPlugin\log4php\configurators\LoggerConfiguratorDefault' => '/configurators/LoggerConfiguratorDefault.php',

		// Filters
		'MuckiLogPlugin\log4php\filters\LoggerFilterDenyAll' => '/filters/LoggerFilterDenyAll.php',
		'MuckiLogPlugin\log4php\filters\LoggerFilterLevelMatch' => '/filters/LoggerFilterLevelMatch.php',
		'MuckiLogPlugin\log4php\filters\LoggerFilterLevelRange' => '/filters/LoggerFilterLevelRange.php',
		'MuckiLogPlugin\log4php\filters\LoggerFilterStringMatch' => '/filters/LoggerFilterStringMatch.php',

		// Helpers
		'MuckiLogPlugin\log4php\helpers\LoggerFormattingInfo' => '/helpers/LoggerFormattingInfo.php',
		'MuckiLogPlugin\log4php\helpers\LoggerOptionConverter' => '/helpers/LoggerOptionConverter.php',
		'MuckiLogPlugin\log4php\helpers\LoggerPatternParser' => '/helpers/LoggerPatternParser.php',
		'MuckiLogPlugin\log4php\helpers\LoggerUtils' => '/helpers/LoggerUtils.php',
	
		// Pattern converters
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverter' => '/pattern/LoggerPatternConverter.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterClass' => '/pattern/LoggerPatternConverterClass.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterCookie' => '/pattern/LoggerPatternConverterCookie.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterDate' => '/pattern/LoggerPatternConverterDate.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterEnvironment' => '/pattern/LoggerPatternConverterEnvironment.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterFile' => '/pattern/LoggerPatternConverterFile.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLevel' => '/pattern/LoggerPatternConverterLevel.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLine' => '/pattern/LoggerPatternConverterLine.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLiteral' => '/pattern/LoggerPatternConverterLiteral.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLocation' => '/pattern/LoggerPatternConverterLocation.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterLogger' => '/pattern/LoggerPatternConverterLogger.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMDC' => '/pattern/LoggerPatternConverterMDC.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMessage' => '/pattern/LoggerPatternConverterMessage.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterMethod' => '/pattern/LoggerPatternConverterMethod.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterNDC' => '/pattern/LoggerPatternConverterNDC.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterNewLine' => '/pattern/LoggerPatternConverterNewLine.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterProcess' => '/pattern/LoggerPatternConverterProcess.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterRelative' => '/pattern/LoggerPatternConverterRelative.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterRequest' => '/pattern/LoggerPatternConverterRequest.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterServer' => '/pattern/LoggerPatternConverterServer.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSession' => '/pattern/LoggerPatternConverterSession.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSessionID' => '/pattern/LoggerPatternConverterSessionID.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterSuperglobal' => '/pattern/LoggerPatternConverterSuperglobal.php',
		'MuckiLogPlugin\log4php\pattern\LoggerPatternConverterThrowable' => '/pattern/LoggerPatternConverterThrowable.php',
		
		// Layouts
		'MuckiLogPlugin\log4php\layouts\LoggerLayoutHtml' => '/layouts/LoggerLayoutHtml.php',
		'MuckiLogPlugin\log4php\layouts\LoggerLayoutPattern' => '/layouts/LoggerLayoutPattern.php',
		'MuckiLogPlugin\log4php\layouts\LoggerLayoutSerialized' => '/layouts/LoggerLayoutSerialized.php',
		'MuckiLogPlugin\log4php\layouts\LoggerLayoutSimple' => '/layouts/LoggerLayoutSimple.php',
		'MuckiLogPlugin\log4php\layouts\LoggerLayoutTTCC' => '/layouts/LoggerLayoutTTCC.php',
		'MuckiLogPlugin\log4php\layouts\LoggerLayoutXml' => '/layouts/LoggerLayoutXml.php',
		
		// Renderers
		'MuckiLogPlugin\log4php\renderers\LoggerRendererDefault' => '/renderers/LoggerRendererDefault.php',
		'MuckiLogPlugin\log4php\renderers\LoggerRendererException' => '/renderers/LoggerRendererException.php',
		'MuckiLogPlugin\log4php\renderers\LoggerRendererMap' => '/renderers/LoggerRendererMap.php',
		'MuckiLogPlugin\log4php\renderers\LoggerRenderer' => '/renderers/LoggerRenderer.php',
	);
	
	/**
	 * Loads a class.
	 * @param string $className The name of the class to load.
	 */
	public static function autoload($className) {
		if(isset(self::$classes[$className])) {
			include dirname(__FILE__) . self::$classes[$className];
		}
	}
}
