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
namespace MuckiLogPlugin\Log4php;

if (function_exists('__autoload')) {
	trigger_error("log4php: It looks like your code is using an __autoload() function. log4php uses spl_autoload_register() which will bypass your __autoload() function and may break autoloading.", E_USER_WARNING);
}

spl_autoload_register(array('MuckiLogPlugin\Log4php\LoggerAutoloader', 'autoload'));

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
		'MuckiLogPlugin\Log4php\LoggerAppender' => '/LoggerAppender.php',
		'MuckiLogPlugin\Log4php\LoggerAppenderPool' => '/LoggerAppenderPool.php',
		'MuckiLogPlugin\Log4php\LoggerConfigurable' => '/LoggerConfigurable.php',
		'MuckiLogPlugin\Log4php\LoggerConfigurator' => '/LoggerConfigurator.php',
		'MuckiLogPlugin\Log4php\LoggerException' => '/LoggerException.php',
		'MuckiLogPlugin\Log4php\LoggerFilter' => '/LoggerFilter.php',
		'MuckiLogPlugin\Log4php\LoggerHierarchy' => '/LoggerHierarchy.php',
		'MuckiLogPlugin\Log4php\LoggerLevel' => '/LoggerLevel.php',
		'MuckiLogPlugin\Log4php\LoggerLocationInfo' => '/LoggerLocationInfo.php',
		'MuckiLogPlugin\Log4php\LoggerLoggingEvent' => '/LoggerLoggingEvent.php',
		'MuckiLogPlugin\Log4php\LoggerMDC' => '/LoggerMDC.php',
		'MuckiLogPlugin\Log4php\LoggerNDC' => '/LoggerNDC.php',
		'MuckiLogPlugin\Log4php\LoggerLayout' => '/LoggerLayout.php',
		'MuckiLogPlugin\Log4php\LoggerReflectionUtils' => '/LoggerReflectionUtils.php',
		'MuckiLogPlugin\Log4php\LoggerRoot' => '/LoggerRoot.php',
		'MuckiLogPlugin\Log4php\LoggerThrowableInformation' => '/LoggerThrowableInformation.php',
		
		// Appenders
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderConsole' => '/Appenders/LoggerAppenderConsole.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderDailyFile' => '/Appenders/LoggerAppenderDailyFile.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho' => '/Appenders/LoggerAppenderEcho.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderFile' => '/Appenders/LoggerAppenderFile.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderMail' => '/Appenders/LoggerAppenderMail.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderMailEvent' => '/Appenders/LoggerAppenderMailEvent.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderMongoDB' => '/Appenders/LoggerAppenderMongoDB.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderNull' => '/Appenders/LoggerAppenderNull.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderFirePHP' => '/Appenders/LoggerAppenderFirePHP.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderPDO' => '/Appenders/LoggerAppenderPDO.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderPhp' => '/Appenders/LoggerAppenderPhp.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderRollingFile' => '/Appenders/LoggerAppenderRollingFile.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderSocket' => '/Appenders/LoggerAppenderSocket.php',
		'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderSyslog' => '/Appenders/LoggerAppenderSyslog.php',
		
		// Configurators
		'MuckiLogPlugin\Log4php\configurators\LoggerConfigurationAdapter' => '/configurators/LoggerConfigurationAdapter.php',
		'MuckiLogPlugin\Log4php\configurators\LoggerConfigurationAdapterINI' => '/configurators/LoggerConfigurationAdapterINI.php',
		'MuckiLogPlugin\Log4php\configurators\LoggerConfigurationAdapterPHP' => '/configurators/LoggerConfigurationAdapterPHP.php',
		'MuckiLogPlugin\Log4php\configurators\LoggerConfigurationAdapterXML' => '/configurators/LoggerConfigurationAdapterXML.php',
		'MuckiLogPlugin\Log4php\configurators\LoggerConfiguratorDefault' => '/configurators/LoggerConfiguratorDefault.php',

		// Filters
		'MuckiLogPlugin\Log4php\filters\LoggerFilterDenyAll' => '/filters/LoggerFilterDenyAll.php',
		'MuckiLogPlugin\Log4php\filters\LoggerFilterLevelMatch' => '/filters/LoggerFilterLevelMatch.php',
		'MuckiLogPlugin\Log4php\filters\LoggerFilterLevelRange' => '/filters/LoggerFilterLevelRange.php',
		'MuckiLogPlugin\Log4php\filters\LoggerFilterStringMatch' => '/filters/LoggerFilterStringMatch.php',

		// Helpers
		'MuckiLogPlugin\Log4php\helpers\LoggerFormattingInfo' => '/helpers/LoggerFormattingInfo.php',
		'MuckiLogPlugin\Log4php\helpers\LoggerOptionConverter' => '/helpers/LoggerOptionConverter.php',
		'MuckiLogPlugin\Log4php\helpers\LoggerPatternParser' => '/helpers/LoggerPatternParser.php',
		'MuckiLogPlugin\Log4php\helpers\LoggerUtils' => '/helpers/LoggerUtils.php',
	
		// Pattern converters
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverter' => '/pattern/LoggerPatternConverter.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterClass' => '/pattern/LoggerPatternConverterClass.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterCookie' => '/pattern/LoggerPatternConverterCookie.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterDate' => '/pattern/LoggerPatternConverterDate.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterEnvironment' => '/pattern/LoggerPatternConverterEnvironment.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterFile' => '/pattern/LoggerPatternConverterFile.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLevel' => '/pattern/LoggerPatternConverterLevel.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLine' => '/pattern/LoggerPatternConverterLine.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLiteral' => '/pattern/LoggerPatternConverterLiteral.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLocation' => '/pattern/LoggerPatternConverterLocation.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterLogger' => '/pattern/LoggerPatternConverterLogger.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMDC' => '/pattern/LoggerPatternConverterMDC.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMessage' => '/pattern/LoggerPatternConverterMessage.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterMethod' => '/pattern/LoggerPatternConverterMethod.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterNDC' => '/pattern/LoggerPatternConverterNDC.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterNewLine' => '/pattern/LoggerPatternConverterNewLine.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterProcess' => '/pattern/LoggerPatternConverterProcess.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterRelative' => '/pattern/LoggerPatternConverterRelative.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterRequest' => '/pattern/LoggerPatternConverterRequest.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterServer' => '/pattern/LoggerPatternConverterServer.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSession' => '/pattern/LoggerPatternConverterSession.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSessionID' => '/pattern/LoggerPatternConverterSessionID.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterSuperglobal' => '/pattern/LoggerPatternConverterSuperglobal.php',
		'MuckiLogPlugin\Log4php\pattern\LoggerPatternConverterThrowable' => '/pattern/LoggerPatternConverterThrowable.php',
		
		// Layouts
		'MuckiLogPlugin\Log4php\layouts\LoggerLayoutHtml' => '/layouts/LoggerLayoutHtml.php',
		'MuckiLogPlugin\Log4php\layouts\LoggerLayoutPattern' => '/layouts/LoggerLayoutPattern.php',
		'MuckiLogPlugin\Log4php\layouts\LoggerLayoutSerialized' => '/layouts/LoggerLayoutSerialized.php',
		'MuckiLogPlugin\Log4php\layouts\LoggerLayoutSimple' => '/layouts/LoggerLayoutSimple.php',
		'MuckiLogPlugin\Log4php\layouts\LoggerLayoutTTCC' => '/layouts/LoggerLayoutTTCC.php',
		'MuckiLogPlugin\Log4php\layouts\LoggerLayoutXml' => '/layouts/LoggerLayoutXml.php',
		
		// Renderers
		'MuckiLogPlugin\Log4php\Renderers\LoggerRendererDefault' => '/renderers/LoggerRendererDefault.php',
		'MuckiLogPlugin\Log4php\Renderers\LoggerRendererException' => '/renderers/LoggerRendererException.php',
		'MuckiLogPlugin\Log4php\Renderers\LoggerRendererMap' => '/renderers/LoggerRendererMap.php',
		'MuckiLogPlugin\Log4php\Renderers\LoggerRenderer' => '/renderers/LoggerRenderer.php',
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
