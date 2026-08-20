<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

use PHPUnit\Framework\TestCase;

class LogViewerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/muwa-logviewer-' . uniqid('', true);
        mkdir($this->logDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->logDir);
    }

    private function createLogViewer(): LogViewer
    {
        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('getLogPath')->willReturn($this->logDir);

        return new LogViewer($settings);
    }

    public function testGetRealPathReturnsPathForExistingLogFile(): void
    {
        file_put_contents($this->logDir . '/dev.log', "hello\n");
        $logViewer = $this->createLogViewer();

        static::assertSame(
            realpath($this->logDir . '/dev.log'),
            $logViewer->getRealPath('dev.log')
        );
    }

    public function testGetRealPathRejectsNonLogExtension(): void
    {
        file_put_contents($this->logDir . '/secret.txt', "secret\n");
        $logViewer = $this->createLogViewer();

        $this->expectException(\RuntimeException::class);
        $logViewer->getRealPath('secret.txt');
    }

    public function testGetRealPathRejectsTraversalAttempt(): void
    {
        $logViewer = $this->createLogViewer();

        $this->expectException(\RuntimeException::class);
        $logViewer->getRealPath('../../../etc/passwd');
    }

    public function testGetRealPathRejectsMissingFile(): void
    {
        $logViewer = $this->createLogViewer();

        $this->expectException(\RuntimeException::class);
        $logViewer->getRealPath('does-not-exist.log');
    }

    public function testListFilesReturnsOnlyLogFilesSortedByMtimeDesc(): void
    {
        file_put_contents($this->logDir . '/old.log', "a\n");
        file_put_contents($this->logDir . '/new.log', "bb\n");
        file_put_contents($this->logDir . '/ignore.txt', "nope\n");
        touch($this->logDir . '/old.log', 1000);
        touch($this->logDir . '/new.log', 2000);

        $files = $this->createLogViewer()->listFiles();

        static::assertCount(2, $files);
        static::assertSame('new.log', $files[0]['name']);
        static::assertSame('old.log', $files[1]['name']);
        static::assertSame(3, $files[0]['size']);
        static::assertSame(2000, $files[0]['mtime']);
    }

    private function writeNumberedLines(string $name, int $count): string
    {
        $content = '';
        for ($i = 1; $i <= $count; $i++) {
            $content .= 'line' . $i . "\n";
        }
        file_put_contents($this->logDir . '/' . $name, $content);

        return $this->logDir . '/' . $name;
    }

    public function testReadTailReturnsLastLines(): void
    {
        $path = $this->writeNumberedLines('dev.log', 10);
        $size = (int) filesize($path);

        $chunk = $this->createLogViewer()->readTail('dev.log', 3);

        static::assertSame("line8\nline9\nline10\n", $chunk['content']);
        static::assertSame(3, $chunk['lines']);
        static::assertTrue($chunk['hasMore']);
        static::assertSame($size, $chunk['size']);
        static::assertSame($size - strlen($chunk['content']), $chunk['startByte']);
    }

    public function testReadTailWholeFileHasNoMore(): void
    {
        $this->writeNumberedLines('dev.log', 4);

        $chunk = $this->createLogViewer()->readTail('dev.log', 100);

        static::assertSame("line1\nline2\nline3\nline4\n", $chunk['content']);
        static::assertFalse($chunk['hasMore']);
        static::assertSame(0, $chunk['startByte']);
    }

    public function testReadTailLoadMoreReturnsOlderLines(): void
    {
        $this->writeNumberedLines('dev.log', 10);
        $logViewer = $this->createLogViewer();

        $first = $logViewer->readTail('dev.log', 3);
        $second = $logViewer->readTail('dev.log', 3, $first['startByte']);

        static::assertSame("line5\nline6\nline7\n", $second['content']);
        static::assertTrue($second['hasMore']);
    }
}
