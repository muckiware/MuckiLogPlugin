<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

final readonly class RootReference
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $entityName,
        public string $entityId,
        public ?string $subEntity,
        public array $context,
    ) {
    }
}
