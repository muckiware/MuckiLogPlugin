<?php declare(strict_types=1);

namespace MuckiLogPlugin\Core\AdminChangeLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(AdminChangeLogEntity $entity)
 * @method void set(string $key, AdminChangeLogEntity $entity)
 * @method AdminChangeLogEntity[] getIterator()
 * @method AdminChangeLogEntity[] getElements()
 * @method AdminChangeLogEntity|null get(string $key)
 * @method AdminChangeLogEntity|null first()
 * @method AdminChangeLogEntity|null last()
 */
class AdminChangeLogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'muwa_admin_change_log_collection';
    }

    protected function getExpectedClass(): string
    {
        return AdminChangeLogEntity::class;
    }
}
