<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

final readonly class ParentLink
{
    public const KIND_TRANSLATION = 'translation';
    public const KIND_CHILD = 'child';
    public const KIND_MAPPING = 'mapping';

    /**
     * @param 'translation'|'child'|'mapping' $kind
     */
    public function __construct(
        public string $parentEntity,
        public string $fkStorageName,
        public string $fkPropertyName,
        public string $kind,
    ) {
    }
}
