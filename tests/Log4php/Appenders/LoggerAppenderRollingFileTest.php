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
namespace MuckiLogPlugin\Log4php\Appenders;

use PHPUnit\Framework\TestCase;

use MuckiLogPlugin\Log4php\Logger;
use MuckiLogPlugin\tests\Log4php\LoggerTestHelper;
use MuckiLogPlugin\Log4php\layouts\LoggerLayoutSimple;

/**
 * @group appenders
 */
class LoggerAppenderRollingFileTest extends TestCase
{
	const WARNING_MASSAGE = 'WARNING - my messageXYZ';
    const FILENAME_COMPRESS = 'TEST-rolling-with-compression.log';

	
	protected function setUp(): void
    {
		// Reset the shared, static logger hierarchy so this test does not inherit
		// a polluted root level (e.g. ERROR set by LoggerTest) which would
		// suppress the WARNING messages and prevent the log file from being created.
		Logger::resetConfiguration();
		$this->removeTestFiles();
	}
	
	public function testRequiresLayout()
    {
		$appender = new LoggerAppenderRollingFile();
		self::assertTrue($appender->requiresLayout());
	}

	public function testMaxFileSize()
    {
		$appender = new LoggerAppenderRollingFile("mylogger");

		$appender->setMaxFileSize('1KB');
		self::assertEquals(1024, $appender->getMaxFileSize());

		$appender->setMaxFileSize('2KB');
		self::assertEquals(2048, $appender->getMaxFileSize());

		$appender->setMaxFileSize('1MB');
		self::assertEquals(1048576, $appender->getMaxFileSize());

		$appender->setMaxFileSize('3MB');
		self::assertEquals(3145728, $appender->getMaxFileSize());

		$appender->setMaxFileSize('1GB');
		self::assertEquals(1073741824, $appender->getMaxFileSize());

		$appender->setMaxFileSize('10000');
		self::assertEquals(10000, $appender->getMaxFileSize());

		$appender->setMaxFileSize('100.5');
		self::assertEquals(100, $appender->getMaxFileSize());

		$appender->setMaxFileSize('1000.6');
		self::assertEquals(1000, $appender->getMaxFileSize());

		$appender->setMaxFileSize('1.5MB');
		self::assertEquals(1048576, $appender->getMaxFileSize());
	}

    /**
     * @param string $testFileName
     * @return LoggerAppenderRollingFile
     */
	private function createRolloverAppender(string $testFileName='TEST-rolling.log'): LoggerAppenderRollingFile
    {
		$layout = new LoggerLayoutSimple();
		
		$appender = new LoggerAppenderRollingFile("mylogger");
		$appender->setFile(LoggerTestHelper::getTestDirPath().'/'.$testFileName);
		$appender->setLayout($layout);
		$appender->setMaxFileSize('1KB');
		$appender->setMaxBackupIndex(2);
		$appender->activateOptions();
		
		return $appender;
	}

	public function testSimpleLogging()
    {
		$appender = $this->createRolloverAppender();
		
		$event = LoggerTestHelper::getWarningEvent("my message123");
		
		for($i = 0; $i < 1000; $i++) {
			$appender->append($event);
		}

		$appender->append(LoggerTestHelper::getWarningEvent("my messageXYZ"));
		$appender->close();

		$file = LoggerTestHelper::getTestDirPath().'/TEST-rolling.log';
		$data = file($file);
		$line = $data[count($data)-1];
		$e = "WARNING - my messageXYZ".PHP_EOL;
		self::assertEquals($e, $line);

		$file = LoggerTestHelper::getTestDirPath().'/TEST-rolling.log.1';
		$this->checkFileContent($file);

		$file = LoggerTestHelper::getTestDirPath().'/TEST-rolling.log.2';
		$this->checkFileContent($file);

		// Should not roll over three times
		$this->assertFalse(file_exists(LoggerTestHelper::getTestDirPath().'/TEST-rolling.log.3'));
	}
	
	public function testLoggingViaLogger()
    {
		$logger = Logger::getLogger('mycat');
		$logger->setAdditivity(false);
		$logger->addAppender($this->createRolloverAppender());

		for($i = 0; $i < 1000; $i++) {
			$logger->warning("my message123");
		}

		$logger->warning("my messageXYZ");

		$file = LoggerTestHelper::getTestDirPath().'/TEST-rolling.log';
		$data = file($file);

		$line = $data[count($data)-1];
		$e = "WARNING - my messageXYZ".PHP_EOL;
		self::assertEquals($e, $line);

		$file = LoggerTestHelper::getTestDirPath().'/TEST-rolling.log.1';
		$this->checkFileContent($file);

		$file = LoggerTestHelper::getTestDirPath().'/TEST-rolling.log.2';
		$this->checkFileContent($file);

		$this->assertFalse(
            file_exists(LoggerTestHelper::getTestDirPath().'/TEST-rolling.log.3'),
            'should not roll over three times'
        );
	}
	
	public function testRolloverWithCompression()
    {
		$logger = Logger::getLogger('mycat');
		$logger->setAdditivity(false);

		$appender = $this->createRolloverAppender(self::FILENAME_COMPRESS);
		$appender->setCompress(true);
		
		$logger->addAppender($appender);
		
		for($i = 0; $i < 1000; $i++) {
			$logger->warning(self::WARNING_MASSAGE. $i);
		}
		
		$logger->warning("my messageXYZ");

		$file = LoggerTestHelper::getTestDirPath().'/'.self::FILENAME_COMPRESS;
		$data = file($file);
		
		$line = $data[count($data)-1];
		$e = self::WARNING_MASSAGE.PHP_EOL;
		self::assertEquals($e, $line);

		$firstCompressedRollingFile = LoggerTestHelper::getTestDirPath().'/'.self::FILENAME_COMPRESS.'.1.gz';
		$this->assertTrue(file_exists($firstCompressedRollingFile),self::FILENAME_COMPRESS.'.1.gz not found');

		$firstUncompressedRollingField = LoggerTestHelper::getTestDirPath().'/'.self::FILENAME_COMPRESS.'.1';
		$this->assertFalse(
            file_exists($firstUncompressedRollingField),
            self::FILENAME_COMPRESS.'.1 should be replaced by compressed'
        );
		
		$secondCompressedRollingFile = LoggerTestHelper::getTestDirPath().'/'.self::FILENAME_COMPRESS.'.2.gz';
		$this->assertTrue(file_exists($secondCompressedRollingFile), self::FILENAME_COMPRESS.'.2.gz not found');
		
		$secondUncompressedRollingField = LoggerTestHelper::getTestDirPath().'/'.self::FILENAME_COMPRESS.'.2';
		$this->assertFalse(
            file_exists($secondUncompressedRollingField),
            self::FILENAME_COMPRESS.'.2 should be replaced by compressed'
        );
	}

	private function checkFileContent($file): void
    {
		$data = file($file);
		$this->checkText($data);
	}

	private function checkText($text): void
    {
		$e = "WARNING - my message123".PHP_EOL;
		foreach($text as $r) {
			self::assertEquals($e, $r);
		}
	}
	
	protected function tearDown(): void
    {
		// Flush/close appenders held by the shared logger and reset the hierarchy
		// so the next test starts from a clean, deterministic state.
		Logger::resetConfiguration();
		$this->removeTestFiles();
	}

	/**
	 * Removes all log files (and rolled-over backups) produced by this test so
	 * repeated runs and rollover assertions stay deterministic.
	 */
	private function removeTestFiles(): void
    {
		$dir = LoggerTestHelper::getTestDirPath();
		$files = array(
			$dir.'/TEST-rolling.log',
			$dir.'/TEST-rolling.log.1',
			$dir.'/TEST-rolling.log.2',
			$dir.'/TEST-rolling.log.3',
			$dir.'/'.self::FILENAME_COMPRESS,
			$dir.'/'.self::FILENAME_COMPRESS.'.1',
			$dir.'/'.self::FILENAME_COMPRESS.'.2',
			$dir.'/'.self::FILENAME_COMPRESS.'.1.gz',
			$dir.'/'.self::FILENAME_COMPRESS.'.2.gz',
		);

		foreach ($files as $file) {
			if (is_file($file)) {
				unlink($file);
			}
		}
	}
}
