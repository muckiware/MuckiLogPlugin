<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use MuckiLogPlugin\ChangeLog\ChangeLogWriter;
use MuckiLogPlugin\ChangeLog\SensitiveFieldMasker;
use MuckiLogPlugin\ChangeLog\SourceResolver;
use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\ChangeLogCommit;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use MuckiLogPlugin\ChangeLog\ValueNormalizer;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SystemConfig\Event\BeforeSystemConfigMultipleChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Logs changes to the Shopware system configuration, which is written via DBAL and bypasses the DAL.
 *
 * Version check (6.6.8): BeforeSystemConfigMultipleChangedEvent exists in v6.6.8.0 with the same
 * constructor and getters (getConfig(), getSalesChannelId()) as in 6.7, and SystemConfigService::setMultiple()
 * dispatches it before writing. Verified against the v6.6.8.0 sources on GitHub.
 *
 * The event is dispatched before the values are stored, so the old values are read raw from the database
 * (SystemConfigService::get() would return inherited values).
 */
final class SystemConfigChangeSubscriber implements EventSubscriberInterface
{
    public const ENTITY_NAME = 'system_config';

    public function __construct(
        private readonly ChangeLogConfig $config,
        private readonly SensitiveFieldMasker $masker,
        private readonly ValueNormalizer $normalizer,
        private readonly SourceResolver $sourceResolver,
        private readonly ChangeLogWriter $writer,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
        private readonly EntityRepository $salesChannelRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BeforeSystemConfigMultipleChangedEvent::class => ['onBeforeChanged', -1000],
        ];
    }

    public function onBeforeChanged(BeforeSystemConfigMultipleChangedEvent $event): void
    {
        try {
            if (!$this->config->isActive()
                || !\in_array(self::ENTITY_NAME, $this->config->getTrackedEntities(), true)
            ) {
                return;
            }

            $commits = $this->buildCommits($event->getConfig(), $event->getSalesChannelId());
            if ($commits === []) {
                return;
            }

            $context = $this->requestStack->getMainRequest()?->attributes->get(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);
            $context = $context instanceof Context ? $context : Context::createDefaultContext();

            $this->writer->write($commits, $this->sourceResolver->resolve($context), $context);
        } catch (\Throwable $e) {
            $this->logger->error(
                \sprintf('Admin change log: could not capture system config change: %s', $e->getMessage()),
                ['muwa', 'admin-change-log']
            );
        }
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<ChangeLogCommit>
     */
    private function buildCommits(array $values, ?string $salesChannelId): array
    {
        if ($values === []) {
            return [];
        }

        $old = $this->loadOldValues(array_map('strval', array_keys($values)), $salesChannelId);
        $byDomain = [];
        foreach ($values as $key => $newValue) {
            $key = (string) $key;
            $change = $this->buildChange($key, $old[mb_strtolower($key)] ?? null, $newValue, $salesChannelId);
            if ($change !== null) {
                $byDomain[$this->domainOf($key)][] = $change;
            }
        }

        $commits = [];
        foreach ($byDomain as $domain => $changes) {
            $commits[] = new ChangeLogCommit(
                self::ENTITY_NAME,
                $domain . '|' . ($salesChannelId ?? 'global'),
                ChangeAction::Update,
                $changes,
                [],
                $this->buildLabel((string) $domain, $salesChannelId)
            );
        }

        return $commits;
    }

    private function buildChange(string $key, mixed $oldValue, mixed $newValue, ?string $salesChannelId): ?FieldChange
    {
        $old = $this->normalizer->normalize($oldValue);
        $new = $this->normalizer->normalize($newValue);
        if ($old === $new) {
            return null;
        }

        $context = $salesChannelId === null ? [] : ['salesChannelId' => $salesChannelId];
        $dot = strrpos($key, '.');
        $segment = $dot === false ? $key : substr($key, $dot + 1);
        if ($this->masker->isSensitive($segment) || $this->masker->isSensitive($key)) {
            return new FieldChange($key, null, $context, SensitiveFieldMasker::MASK, SensitiveFieldMasker::MASK, true);
        }

        $masked = false;
        $old = $this->masker->maskValue($old, $masked);
        $new = $this->masker->maskValue($new, $masked);

        return new FieldChange($key, null, $context, $old, $new, $masked);
    }

    /**
     * @param list<string> $keys
     *
     * @return array<string, mixed> old values keyed by lower-cased configuration key
     */
    private function loadOldValues(array $keys, ?string $salesChannelId): array
    {
        $rows = $this->connection->fetchAllKeyValue(
            'SELECT configuration_key, configuration_value FROM system_config
             WHERE configuration_key IN (:keys) AND sales_channel_id <=> :sc',
            [
                'keys' => $keys,
                'sc' => $salesChannelId === null ? null : Uuid::fromHexToBytes($salesChannelId),
            ],
            ['keys' => ArrayParameterType::STRING]
        );

        $old = [];
        foreach ($rows as $key => $json) {
            $decoded = \is_string($json) ? json_decode($json, true) : null;
            $old[mb_strtolower((string) $key)] = \is_array($decoded) ? ($decoded['_value'] ?? null) : null;
        }

        return $old;
    }

    private function domainOf(string $key): string
    {
        $pos = strrpos($key, '.');

        return $pos === false ? $key : substr($key, 0, $pos);
    }

    private function buildLabel(string $domain, ?string $salesChannelId): string
    {
        if ($salesChannelId === null) {
            return \sprintf('Einstellungen %s (Alle Verkaufskanäle)', $domain);
        }

        return \sprintf('Einstellungen %s (%s)', $domain, $this->salesChannelName($salesChannelId));
    }

    private function salesChannelName(string $salesChannelId): string
    {
        try {
            $channel = $this->salesChannelRepository
                ->search(new Criteria([$salesChannelId]), Context::createDefaultContext())
                ->first();
            $name = $channel?->getTranslation('name') ?? $channel?->get('name');

            return \is_string($name) && $name !== '' ? $name : $salesChannelId;
        } catch (\Throwable) {
            return $salesChannelId;
        }
    }
}
