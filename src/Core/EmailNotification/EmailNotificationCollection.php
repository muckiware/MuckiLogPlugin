<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\Core\EmailNotification;

use MuckiLogPlugin\Core\LoggingEvent\LoggingEventEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(EmailNotificationEntity $entity)
 * @method void set(string $key, EmailNotificationEntity $entity)
 * @method EmailNotificationEntity[] getIterator()
 * @method EmailNotificationEntity[]  getElements()
 * @method EmailNotificationEntity|null get(string $key)
 * @method EmailNotificationEntity|null first()
 * @method EmailNotificationEntity|null last()
 */
class EmailNotificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return EmailNotificationEntity::class;
    }
}
