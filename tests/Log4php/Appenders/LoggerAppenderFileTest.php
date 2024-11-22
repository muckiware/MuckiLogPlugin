<?php
/**
 * Licensed to the Apache Software Foundation (ASF) under one or more
 * contributor license agreements.  See the NOTICE file distributed with
 * this work for additional information regarding copyright ownership.
 * The ASF licenses this file to You under the Apache License, Version 2.0
 * (the "License"); you may not use this file except in compliance with
 * the License.  You may obtain a copy of the License at
 *
 *	  http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @category   tests
 * @package	   log4php
 * @subpackage appenders
 * @license	   http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @version    $Revision$
 * @link       http://logging.apache.org/log4php
 *
 * changed by muckiware (c)2024
 * @link https://github.com/muckiware/MuckiLogPlugin
 */
namespace MuckiLogPlugin\Log4php\Appenders;

use PHPUnit\Framework\TestCase;

use MuckiLogPlugin\Log4php\Logger;
use MuckiLogPlugin\Log4php\LoggerTestHelper;

/**
 * @group appenders
 */
class LoggerAppenderFileTest extends TestCase
{
	private array $config1 = array(
		'rootLogger' => array(
			'appenders' => array('default'),
		),
		'appenders' => array(
			'default' => array(
				'class' => 'MuckiLogPlugin\Log4php\Appenders\LoggerAppenderFile',
				'layout' => array(
					'class' => 'MuckiLogPlugin\Log4php\layouts\LoggerLayoutSimple'
				),
				'params' => array()
			)
		)
	);
	
	private string $testPath;
	
	public function setUp(): void
    {
        $this->testPath = LoggerTestHelper::getTestDirPath().'/TEST.txt';

		Logger::resetConfiguration();
		if(file_exists($this->testPath)) {
			unlink($this->testPath);
		}
	}
	
	public function tearDown(): void
    {
		Logger::resetConfiguration();
		if(file_exists($this->testPath)) {
			unlink($this->testPath);
		}
	}
	
	public function testRequiresLayout()
    {
		$appender = new LoggerAppenderFile();
		self::assertTrue($appender->requiresLayout());
	}
	
	public function testActivationDoesNotCreateTheFile()
    {
		$path = LoggerTestHelper::getTestDirPath().'/doesnotexisthopefully.log';
		@unlink($path);
		$appender = new LoggerAppenderFile();
		$appender->setFile($path);
		$appender->activateOptions();

		self::assertFalse(file_exists($path));

		$event = LoggerTestHelper::getInfoEvent('bla');
		$appender->append($event);

		self::assertTrue(file_exists($path));
	}
	
	public function testSimpleLogging()
    {
		$config = $this->config1;
		$config['appenders']['default']['params']['file'] = $this->testPath;

		Logger::configure($config);

		$logger = Logger::getRootLogger();
		$logger->info('This is a test');

		$expected = "INFO - This is a test" . PHP_EOL;
		$actual = file_get_contents($this->testPath);
		$this->assertSame($expected, $actual);
	}
	
	public function testAppendFlagTrue()
    {
		$config = $this->config1 ?? [];
		$config['appenders']['default']['params']['file'] = $this->testPath;
		$config['appenders']['default']['params']['append'] = true;

		Logger::configure($config);
		$logger = Logger::getRootLogger();
		$logger->info('This is a test');

		Logger::configure($config);
		$logger = Logger::getRootLogger();
		$logger->info('This is a test');

		$expected = "INFO - This is a test" . PHP_EOL . "INFO - This is a test" . PHP_EOL;
		$actual = file_get_contents($this->testPath);
		$this->assertSame($expected, $actual);
	}
	
	public function testAppendFlagFalse()
    {
		$config = $this->config1;
		$config['appenders']['default']['params']['file'] = $this->testPath;
		$config['appenders']['default']['params']['append'] = false;

		Logger::configure($config);
		$logger = Logger::getRootLogger();
		$logger->info('This is a test');

		Logger::configure($config);
		$logger = Logger::getRootLogger();
		$logger->info('This is a test');

		$expected = "INFO - This is a test" . PHP_EOL;
		$actual = file_get_contents($this->testPath);
		$this->assertSame($expected, $actual);
	}
}
