<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogDefinition;
use MuckiLogPlugin\Core\ConfigPath;
use Shopware\Core\System\SystemConfig\SystemConfigService;

final class ChangeLogConfig
{
    public const DEFAULT_ENTITIES = ['product', 'category', 'order', 'customer', 'cms_page'];
    public const DEFAULT_RETENTION_DAYS = 90;

    public function __construct(private readonly SystemConfigService $config)
    {
    }

    public function isActive(): bool
    {
        return $this->config->getBool(ConfigPath::CONFIG_PATH_ACTIVE_CHANGE_LOG->value);
    }

    /**
     * @return list<string>
     */
    public function getTrackedEntities(): array
    {
        $value = $this->config->get(ConfigPath::CONFIG_PATH_CHANGE_LOG_ENTITIES->value);
        if (!\is_array($value)) {
            return self::DEFAULT_ENTITIES;
        }

        $entities = array_filter(
            $value,
            static fn (mixed $entity): bool => \is_string($entity)
                && $entity !== ''
                && $entity !== AdminChangeLogDefinition::ENTITY_NAME
        );

        return array_values($entities);
    }

    public function getRetentionDays(): int
    {
        $value = $this->config->get(ConfigPath::CONFIG_PATH_CHANGE_LOG_RETENTION_DAYS->value);
        if ($value === null || $value === '' || !is_numeric($value)) {
            return self::DEFAULT_RETENTION_DAYS;
        }

        return max(0, (int) $value);
    }
}
