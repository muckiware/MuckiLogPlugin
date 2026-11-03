<?php declare(strict_types=1);

namespace MuckiLogPlugin\Core\AdminChangeLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserEntity;

class AdminChangeLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $entityName;
    protected string $entityId;
    protected ?string $entityLabel = null;
    protected string $action;
    protected string $sourceType;
    protected ?string $userId = null;
    protected ?string $userName = null;
    protected ?string $integrationId = null;
    protected ?string $salesChannelId = null;
    protected ?string $sourceName = null;
    protected ?string $ipAddress = null;
    /**
     * @var list<array<string, mixed>>|null
     */
    protected ?array $changes = null;
    protected ?UserEntity $user = null;

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getEntityId(): string
    {
        return $this->entityId;
    }

    public function setEntityId(string $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getEntityLabel(): ?string
    {
        return $this->entityLabel;
    }

    public function setEntityLabel(?string $entityLabel): void
    {
        $this->entityLabel = $entityLabel;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): void
    {
        $this->sourceType = $sourceType;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserName(): ?string
    {
        return $this->userName;
    }

    public function setUserName(?string $userName): void
    {
        $this->userName = $userName;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }

    public function setIntegrationId(?string $integrationId): void
    {
        $this->integrationId = $integrationId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getSourceName(): ?string
    {
        return $this->sourceName;
    }

    public function setSourceName(?string $sourceName): void
    {
        $this->sourceName = $sourceName;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getChanges(): ?array
    {
        return $this->changes;
    }

    /**
     * @param list<array<string, mixed>>|null $changes
     */
    public function setChanges(?array $changes): void
    {
        $this->changes = $changes;
    }

    public function getUser(): ?UserEntity
    {
        return $this->user;
    }

    public function setUser(?UserEntity $user): void
    {
        $this->user = $user;
    }
}
