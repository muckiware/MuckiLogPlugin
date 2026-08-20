<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

final class LogViewer implements LogViewerInterface
{
    public function __construct(
        private readonly SettingsInterface $settings
    ) {
    }

    public function getRealPath(string $file): string
    {
        $name = basename($file);
        if ($name === '' || !str_ends_with($name, '.log')) {
            throw new \RuntimeException(sprintf('Invalid log file name "%s".', $file));
        }

        $baseDir = $this->settings->getLogPath();
        $candidate = $baseDir . '/' . $name;

        $real = realpath($candidate);
        $baseReal = realpath($baseDir);

        if ($real === false || $baseReal === false
            || !str_starts_with($real, $baseReal . \DIRECTORY_SEPARATOR)
            || !is_file($real)
            || !is_readable($real)
        ) {
            throw new \RuntimeException(sprintf('Log file "%s" not found.', $file));
        }

        return $real;
    }

    /**
     * @return array<int, array{name: string, size: int, mtime: int}>
     */
    public function listFiles(): array
    {
        $baseDir = $this->settings->getLogPath();
        $paths = glob($baseDir . '/*.log') ?: [];

        $files = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'mtime' => (int) filemtime($path),
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $files;
    }

    /**
     * @return array{content: string, startByte: int, hasMore: bool, size: int, lines: int}
     */
    public function readTail(string $file, int $maxLines, ?int $beforeByte = null): array
    {
        $path = $this->getRealPath($file);
        $size = (int) filesize($path);
        $end = $beforeByte ?? $size;
        $end = max(0, min($end, $size));

        if ($end === 0 || $maxLines < 1) {
            return ['content' => '', 'startByte' => 0, 'hasMore' => false, 'size' => $size, 'lines' => 0];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open log file "%s".', $file));
        }

        $chunkSize = 8192;
        $pos = $end;
        $buffer = '';
        $newlineCount = 0;

        // Rückwärts lesen, bis genug Zeilen-Grenzen (maxLines + 1) oder Dateianfang.
        while ($pos > 0 && $newlineCount <= $maxLines) {
            $read = (int) min($chunkSize, $pos);
            $pos -= $read;
            fseek($handle, $pos);
            $buffer = (string) fread($handle, $read) . $buffer;
            $newlineCount = substr_count($buffer, "\n");
        }
        fclose($handle);

        $hadTrailingNewline = str_ends_with($buffer, "\n");
        $trimmed = $hadTrailingNewline ? substr($buffer, 0, -1) : $buffer;
        $allLines = $trimmed === '' ? [] : explode("\n", $trimmed);

        $kept = array_slice($allLines, -$maxLines);
        $content = implode("\n", $kept);
        if ($content !== '' && $hadTrailingNewline) {
            $content .= "\n";
        }

        $startByte = $end - strlen($content);

        return [
            'content' => $content,
            'startByte' => $startByte,
            'hasMore' => $startByte > 0,
            'size' => $size,
            'lines' => count($kept),
        ];
    }
}
