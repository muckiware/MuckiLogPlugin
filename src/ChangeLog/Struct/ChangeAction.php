<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;

enum ChangeAction: string
{
    case Insert = 'insert';
    case Update = 'update';
    case Delete = 'delete';

    public static function fromOperation(string $op): self
    {
        return match (mb_strtolower($op)) {
            EntityWriteResult::OPERATION_INSERT => self::Insert,
            EntityWriteResult::OPERATION_DELETE => self::Delete,
            default => self::Update,
        };
    }
}
