<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

interface LogViewerInterface
{
    /**
     * @return array<int, array{name: string, size: int, mtime: int}>
     */
    public function listFiles(): array;

    /**
     * @return array{content: string, startByte: int, hasMore: bool, size: int, lines: int}
     */
    public function readTail(string $file, int $maxLines, ?int $beforeByte = null): array;

    public function getRealPath(string $file): string;
}
