<?php declare(strict_types=1);

namespace MuckiLogPlugin\Core\AdminChangeLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\User\UserDefinition;

class AdminChangeLogDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'muwa_admin_change_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return AdminChangeLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return AdminChangeLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('entity_name', 'entityName', 64))
                ->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('entity_id', 'entityId', 255))
                ->addFlags(new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new StringField('entity_label', 'entityLabel', 255))
                ->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new StringField('action', 'action', 16))->addFlags(new Required()),
            (new StringField('source_type', 'sourceType', 16))->addFlags(new Required()),
            new FkField('user_id', 'userId', UserDefinition::class),
            (new StringField('user_name', 'userName', 255))
                ->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new IdField('integration_id', 'integrationId'),
            new IdField('sales_channel_id', 'salesChannelId'),
            (new StringField('source_name', 'sourceName', 255))
                ->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            new StringField('ip_address', 'ipAddress', 45),
            new JsonField('changes', 'changes'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false),
        ]);
    }
}
