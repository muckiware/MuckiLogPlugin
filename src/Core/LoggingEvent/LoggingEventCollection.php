<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\Core\LoggingEvent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(LoggingEventEntity $entity)
 * @method void set(string $key, LoggingEventEntity $entity)
 * @method LoggingEventEntity[] getIterator()
 * @method LoggingEventEntity[]  getElements()
 * @method LoggingEventEntity|null get(string $key)
 * @method LoggingEventEntity|null first()
 * @method LoggingEventEntity|null last()
 */
class LoggingEventCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'muwa_logging_event';
    }

    protected function getExpectedClass(): string
    {
        return LoggingEventEntity::class;
    }
}
