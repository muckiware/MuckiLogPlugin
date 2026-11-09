<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog\Struct;

enum ChangeSourceType: string
{
    case User = 'user';
    case Integration = 'integration';
    case SalesChannel = 'sales_channel';
    case System = 'system';
}
