<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

final readonly class ChangeSource
{
    public function __construct(
        public ChangeSourceType $type,
        public ?string $userId,
        public ?string $userName,
        public ?string $integrationId,
        public ?string $salesChannelId,
        public ?string $sourceName,
        public ?string $ipAddress,
    ) {
    }
}
