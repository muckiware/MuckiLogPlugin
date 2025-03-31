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

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\AllowHtml;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Swag\PayPal\Pos\Api\Webhook\Payload\InventoryBalanceChanged\Updated;

class LoggingEventDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'muwa_logging_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return LoggingEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return LoggingEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('vendor', 'vendor')),
            (new StringField('plugin', 'plugin')),
            (new StringField('loglevel', 'loglevel')),
            (new LongTextField('message', 'message'))->addFlags(new AllowHtml()),
            (new IdField('notification_email_template_id', 'notificationEmailTemplateId'))->addFlags(new Required()),
            (new EmailField('notification_email_receiver', 'notificationEmailReceiver'))->addFlags(new Required()),
            (new EmailField('notification_email_sender', 'notificationEmailSender')),
            new CreatedAtField(),
            new UpdatedAtField()
        ]);
    }
}
