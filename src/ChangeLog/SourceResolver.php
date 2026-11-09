<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ChangeSource;
use MuckiLogPlugin\ChangeLog\Struct\ChangeSourceType;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\AdminSalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Determines who caused a write: admin user, integration, sales channel or system.
 */
final class SourceResolver implements ResetInterface
{
    /** @var array<string, string|null> keyed by "<kind>:<id>" */
    private array $names = [];

    public function __construct(
        private readonly EntityRepository $userRepository,
        private readonly EntityRepository $integrationRepository,
        private readonly EntityRepository $salesChannelRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function reset(): void
    {
        $this->names = [];
    }

    public function resolve(Context $context): ChangeSource
    {
        $ip = $this->requestStack->getMainRequest()?->getClientIp();
        $source = $context->getSource();

        if ($source instanceof AdminSalesChannelApiSource) {
            $source = $source->getOriginalContext()->getSource();
        }

        if ($source instanceof AdminApiSource) {
            return $this->fromAdmin($source, $ip);
        }

        if ($source instanceof SalesChannelApiSource) {
            $id = $source->getSalesChannelId();

            return new ChangeSource(
                ChangeSourceType::SalesChannel,
                null,
                null,
                null,
                $id,
                $this->name('sales_channel', $id),
                $ip
            );
        }

        return new ChangeSource(ChangeSourceType::System, null, null, null, null, null, $ip);
    }

    private function fromAdmin(AdminApiSource $source, ?string $ip): ChangeSource
    {
        $userId = $source->getUserId();
        if ($userId !== null) {
            $name = $this->name('user', $userId);

            return new ChangeSource(ChangeSourceType::User, $userId, $name, null, null, $name, $ip);
        }

        $integrationId = $source->getIntegrationId();
        if ($integrationId !== null) {
            return new ChangeSource(
                ChangeSourceType::Integration,
                null,
                null,
                $integrationId,
                null,
                $this->name('integration', $integrationId),
                $ip
            );
        }

        return new ChangeSource(ChangeSourceType::System, null, null, null, null, null, $ip);
    }

    private function name(string $kind, string $id): ?string
    {
        $key = $kind . ':' . $id;
        if (\array_key_exists($key, $this->names)) {
            return $this->names[$key];
        }

        return $this->names[$key] = $this->load($kind, $id);
    }

    private function load(string $kind, string $id): ?string
    {
        $repository = match ($kind) {
            'user' => $this->userRepository,
            'integration' => $this->integrationRepository,
            default => $this->salesChannelRepository,
        };

        try {
            $entity = $repository->search(new Criteria([$id]), Context::createDefaultContext())->first();
            if ($entity === null) {
                return null;
            }

            return match ($kind) {
                'user' => $this->userName($entity),
                'integration' => $this->nonEmpty($entity->get('label')),
                default => $this->nonEmpty($entity->getTranslation('name') ?? $entity->get('name')),
            };
        } catch (\Throwable) {
            return null;
        }
    }

    private function userName(Entity $user): ?string
    {
        $full = trim(\sprintf('%s %s', $user->get('firstName') ?? '', $user->get('lastName') ?? ''));

        return $full !== '' ? $full : $this->nonEmpty($user->get('username'));
    }

    private function nonEmpty(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }
}
