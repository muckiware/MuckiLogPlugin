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
namespace MuckiLogPlugin\Log4php;

use PHPUnit\Framework\TestCase;
use MuckiLogPlugin\Log4php\configurators\LoggerConfiguratorDefault;
use MuckiLogPlugin\Log4php\LoggerHierarchy;
use MuckiLogPlugin\Log4php\LoggerLevel;

/**
 *
 * @group configurators
 *
 */
 class LoggerConfiguratorTest extends TestCase
 {
     const PHPUNIT_CONFIG_DIR = __DIR__.'/configs';
 	/** Reset configuration after each test. */
 	public function setUp(): void
    {
 		Logger::resetConfiguration();
 	}
 	/** Reset configuration after each test. */
 	public function tearDown(): void
    {
 		Logger::resetConfiguration();
 	}
 	
 	/** Check default setup. */
 	public function testDefaultConfig()
    {
 		Logger::configure();
 		
 		$actual = Logger::getCurrentLoggers();
 		$expected = array();
		$this->assertSame($expected, $actual);

        $appenders = Logger::getRootLogger()->getAllAppenders();
        $this->isType('array', $appenders);
 		$this->assertEquals(count($appenders), 1);
 		
 		$names = array_keys($appenders);
 		$this->assertSame('default', $names[0]);
 		
 		$appender = array_shift($appenders);
 		$this->assertInstanceOf('MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho', $appender);
 		$this->assertSame('default', $appender->getName());
 		
 		$layout = $appender->getLayout();
 		$this->assertInstanceOf('MuckiLogPlugin\Log4php\layouts\LoggerLayoutSimple', $layout);
 		
 		$root = Logger::getRootLogger();
 		$appenders = $root->getAllAppenders();
 		$this->isType('array', $appenders);
 		$this->assertEquals(count($appenders), 1);
		
 		$actual = $root->getLevel();
 		$expected = LoggerLevel::getLevelDebug();
 		$this->assertEquals($expected, $actual);
 	}
// 	public function testInputIsInteger()
//    {
//        $this->expectException(\ErrorException::class);
//        //$this->expectExceptionMessage('Configuration failed. File not found at [12345]. Using default configuration.');
//        $this->expectExceptionMessage('log4php: Configuration failed. File not found at [12345]. Using default configuration.');
//        Logger::configure(12345);
//        $this->assertTrue(true);
// 	}

// 	public function testYAMLFile()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Configuration failed. Unsupported configuration file extension: yml Using default configuration.'
//        );
//		Logger::configure(self::PHPUNIT_CONFIG_DIR.'/config.yml');
//        $this->assertTrue(true);
// 	}

// 	public function testAppenderConfigNotArray()
//    {
// 		$hierachyMock = $this->getMock('LoggerHierarchy', array(), array(), '', false);
//        $LoggerLevelMock = $this->getMock(LoggerLevel::class);
//        $hierachyMock = $this->createMock(LoggerHierarchy::class);
//
// 		$config = array(
//	 		'appenders' => array(
//	            'default',
//	        ),
//        );
//
//        $configurator = new LoggerConfiguratorDefault();
//        $configurator->configure($hierachyMock, $config);
// 	}

// 	public function testNoAppenderClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'No class given for appender [foo]. Skipping appender definition.'
//        );
//
//        Logger::configure(self::PHPUNIT_CONFIG_DIR.'/appenders/config_no_class.xml');
//        $this->assertTrue(true);
// 	}

// 	public function testNotExistingAppenderClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid class [unknownClass] given for appender [foo]. Class does not exist. Skipping appender definition.'
//        );
//
//        Logger::configure(self::PHPUNIT_CONFIG_DIR.'/appenders/config_not_existing_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testInvalidAppenderClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid class [stdClass] given for appender [foo]. Not a valid LoggerAppender class. Skipping appender definition.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_invalid_appender_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNotExistingAppenderFilterClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Nonexistant filter class [Foo] specified on appender [foo]. Skipping filter definition.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_not_existing_filter_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testInvalidAppenderFilterParameter()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Nonexistant option [fooParameter] specified on [MuckiLogPlugin\Log4php\Filters\LoggerFilterStringMatch]. Skipping.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_invalid_filter_parameters.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testInvalidAppenderFilterClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid filter class [stdClass] sepcified on appender [foo]. Skipping filter definition.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_invalid_filter_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNotExistingAppenderLayoutClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Nonexistant layout class [Foo] specified for appender [foo]. Reverting to default layout.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_not_existing_layout_class.xml');
//        $this->assertTrue(true);
// 	}

// 	public function testInvalidAppenderLayoutClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid layout class [stdClass] sepcified for appender [foo]. Reverting to default layout.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_invalid_layout_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNoAppenderLayoutClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//
//        Logger::configure(self::PHPUNIT_CONFIG_DIR . '/appenders/config_no_layout_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testInvalidRenderingClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid class [LoggerAppenderConsole] given for appender [foo]. Class does not exist. Skipping appender definition.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/renderers/config_invalid_rendering_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNoRenderingClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid class [LoggerAppenderConsole] given for appender [foo]. Class does not exist. Skipping appender definition.'
//        );
//
//        Logger::configure(self::PHPUNIT_CONFIG_DIR . '/renderers/config_no_rendering_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNotExistingRenderingClassSet()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid class [LoggerAppenderConsole] given for appender [foo]. Class does not exist. Skipping appender definition.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/renderers/config_not_existing_rendering_class.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testInvalidLoggerAddivity()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid additivity value [4711] specified for logger [myLogger]. Ignoring additivity setting.'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/loggers/config_invalid_additivity.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNotExistingLoggerAppendersClass()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Nonexistnant appender [unknownAppender] linked to logger [myLogger].'
//        );
//
// 		Logger::configure(self::PHPUNIT_CONFIG_DIR . '/loggers/config_not_existing_appenders.xml');
//        $this->assertTrue(true);
// 	}
//
// 	public function testNonexistentFile()
//    {
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Configuration failed. File not found at [hopefully/this/path/doesnt/exist/config.xml]. Using default configuration.'
//        );
//
// 		Logger::configure('hopefully/this/path/doesnt/exist/config.xml');
//        $this->assertTrue(true);
// 	}
//
// 	/** Test correct fallback to the default configuration. */
// 	public function testNonexistentFileFallback()
//    {
// 		@Logger::configure('hopefully/this/path/doesnt/exist/config.xml');
// 		$this->testDefaultConfig();
// 	}
//
// 	public function testAppendersWithLayout()
//    {
// 		ob_start();
// 		Logger::getRootLogger()->info('info');
// 		$actual = ob_get_contents();
// 		ob_end_clean();
//
// 		$expected = "INFO - info";
//  		$this->assertSame(trim($expected), trim($actual));
// 	}
//
//  	public function testThreshold()
// 	{
// 		Logger::configure(array(
// 			'threshold' => 'WARNING',
// 			'rootLogger' => array(
// 				'appenders' => array('default')
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 				),
// 			)
// 		));
//
// 		$actual = Logger::getHierarchy()->getThreshold();
// 		$expected = LoggerLevel::getLevelWarning();
//
// 		self::assertSame($expected, $actual);
// 	}
//
//  	public function testInvalidThreshold()
// 	{
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid threshold value [FOO] specified. Ignoring threshold definition.'
//        );
//
// 		Logger::configure(array(
// 			'threshold' => 'FOO',
// 			'rootLogger' => array(
// 				'appenders' => array('default')
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 				),
// 			)
// 		));
// 	}
//
// 	public function testAppenderThreshold()
// 	{
// 		Logger::configure(array(
// 			'rootLogger' => array(
// 				'appenders' => array('default')
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 					'threshold' => 'INFO'
// 				),
// 			)
// 		));
//
// 		$actual = Logger::getRootLogger()->getAppender('default')->getThreshold();
// 		$expected = LoggerLevel::getLevelInfo();
//
// 		self::assertSame($expected, $actual);
// 	}
//
// 	public function testAppenderInvalidThreshold()
// 	{
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid threshold value [FOO] specified for appender [default]. Ignoring threshold definition.'
//        );
//
// 		Logger::configure(array(
// 			'rootLogger' => array(
// 				'appenders' => array('default')
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 					'threshold' => 'FOO'
// 				),
// 			)
// 		));
// 	}
//
// 	public function testLoggerThreshold()
// 	{
// 		Logger::configure(array(
// 			'rootLogger' => array(
// 				'appenders' => array('default'),
// 				'level' => 'ERROR'
// 			),
// 			'loggers' => array(
// 				'default' => array(
// 					'appenders' => array('default'),
// 		 			'level' => 'WARNING'
// 				)
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 				),
// 			)
// 		));
//
// 		// Check root logger
// 		$actual = Logger::getRootLogger()->getLevel();
// 		$expected = LoggerLevel::getLevelError();
// 		self::assertSame($expected, $actual);
//
// 		// Check default logger
// 		$actual = Logger::getLogger('default')->getLevel();
// 		$expected = LoggerLevel::getLevelWarning();
// 		self::assertSame($expected, $actual);
// 	}
//
// 	public function testInvalidLoggerThreshold()
// 	{
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid level value [FOO] specified for logger [default]. Ignoring level definition.'
//        );
//
// 		Logger::configure(array(
// 			'loggers' => array(
// 				'default' => array(
// 					'appenders' => array('default'),
// 		 			'level' => 'FOO'
// 				)
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 				),
// 			)
// 		));
// 	}

//  	public function testInvalidRootLoggerThreshold()
// 	{
//        $this->expectException(\ErrorException::class);
//        $this->expectExceptionMessage(
//            'Invalid level value [FOO] specified for logger [root]. Ignoring level definition.'
//        );
//
// 		Logger::configure(array(
// 			'rootLogger' => array(
// 				'appenders' => array('default'),
// 				'level' => 'FOO'
// 			),
// 			'appenders' => array(
// 				'default' => array(
// 					'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderEcho',
// 				),
// 			)
// 		));
// 	}
 }
