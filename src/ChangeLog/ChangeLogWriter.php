<?php

declare(strict_types=1);

namespace MuckiLogPlugin\ChangeLog;

use MuckiLogPlugin\ChangeLog\Struct\ChangeLogCommit;
use MuckiLogPlugin\ChangeLog\Struct\ChangeSource;
use MuckiLogPlugin\ChangeLog\Struct\FieldChange;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Persists change log commits with a single create() call; never throws.
 */
final class ChangeLogWriter
{
    public const CONTEXT_STATE_SKIP = 'muwa_change_log.skip';

    private const LABEL_MAX_LENGTH = 255;

    public function __construct(
        private readonly EntityRepository $changeLogRepository,
        private readonly EntityLabelResolver $labelResolver,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<ChangeLogCommit> $commits
     */
    public function write(array $commits, ChangeSource $source, Context $context): void
    {
        if ($commits === []) {
            return;
        }

        try {
            $labels = $this->resolveLabels($commits, $context);
            $payloads = array_map(
                fn (ChangeLogCommit $commit): array => $this->payload($commit, $source, $labels),
                $commits
            );

            $writeContext = Context::createDefaultContext();
            $writeContext->addState(self::CONTEXT_STATE_SKIP);
            $this->changeLogRepository->create($payloads, $writeContext);
        } catch (\Throwable $e) {
            $this->logger->error(
                \sprintf('Admin change log: could not write %d entries: %s', \count($commits), $e->getMessage()),
                ['muwa', 'admin-change-log']
            );
        }
    }

    /**
     * One label lookup per entity type for all commits without a preset label.
     *
     * @param list<ChangeLogCommit> $commits
     *
     * @return array<string, array<string, string>> entityName => id => label
     */
    private function resolveLabels(array $commits, Context $context): array
    {
        $beforeByEntity = [];
        foreach ($commits as $commit) {
            if ($commit->getLabel() === null) {
                $beforeByEntity[$commit->getEntityName()][$commit->getEntityId()] = $commit->getBeforeValues();
            }
        }

        $labels = [];
        foreach ($beforeByEntity as $entityName => $beforeValuesById) {
            $labels[$entityName] = $this->labelResolver->resolveMany($entityName, $beforeValuesById, $context);
        }

        return $labels;
    }

    /**
     * @param array<string, array<string, string>> $labels
     *
     * @return array<string, mixed>
     */
    private function payload(ChangeLogCommit $commit, ChangeSource $source, array $labels): array
    {
        $label = $commit->getLabel() ?? $labels[$commit->getEntityName()][$commit->getEntityId()] ?? null;

        return [
            'id' => Uuid::randomHex(),
            'entityName' => $commit->getEntityName(),
            'entityId' => $commit->getEntityId(),
            'entityLabel' => $label === null ? null : mb_substr($label, 0, self::LABEL_MAX_LENGTH),
            'action' => $commit->getAction()->value,
            'sourceType' => $source->type->value,
            'userId' => $source->userId,
            'userName' => $source->userName,
            'integrationId' => $source->integrationId,
            'salesChannelId' => $source->salesChannelId,
            'sourceName' => $source->sourceName,
            'ipAddress' => $source->ipAddress,
            'changes' => array_map(static fn (FieldChange $change): array => $change->toArray(), $commit->getChanges()),
        ];
    }
}
