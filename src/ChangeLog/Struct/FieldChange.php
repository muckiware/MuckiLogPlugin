<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

final readonly class FieldChange
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $field,
        public ?string $subEntity,
        public array $context,
        public mixed $old,
        public mixed $new,
        public bool $masked = false,
    ) {
    }

    /**
     * @return array{field: string, subEntity: ?string, context: array<string, mixed>, old: mixed, new: mixed, masked: bool}
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'subEntity' => $this->subEntity,
            'context' => $this->context,
            'old' => $this->old,
            'new' => $this->new,
            'masked' => $this->masked,
        ];
    }
}
