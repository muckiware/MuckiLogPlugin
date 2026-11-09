<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Subscriber;

use MuckiLogPlugin\ChangeLog\ChangeLogConfig;
use MuckiLogPlugin\ChangeLog\ChangeLogWriter;
use MuckiLogPlugin\ChangeLog\CommitBuilder;
use MuckiLogPlugin\ChangeLog\EntityGraph;
use MuckiLogPlugin\ChangeLog\FieldChangeExtractor;
use MuckiLogPlugin\ChangeLog\RootResolver;
use MuckiLogPlugin\ChangeLog\SourceResolver;
use MuckiLogPlugin\ChangeLog\Struct\ChangeAction;
use MuckiLogPlugin\ChangeLog\Struct\ChangeLogCommit;
use MuckiLogPlugin\Core\AdminChangeLog\AdminChangeLogDefinition;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Captures DAL writes of tracked entities (and their sub-entities) as admin change log commits.
 */
final class ChangeLogWriteSubscriber implements EventSubscriberInterface
{
    private const SKIPPED_ENTITIES = ['system_config', AdminChangeLogDefinition::ENTITY_NAME];

    public function __construct(
        private readonly ChangeLogConfig $config,
        private readonly EntityGraph $graph,
        private readonly RootResolver $rootResolver,
        private readonly FieldChangeExtractor $extractor,
        private readonly SourceResolver $sourceResolver,
        private readonly ChangeLogWriter $writer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreWriteValidationEvent::class => 'onPreWriteValidation',
            EntityWrittenContainerEvent::class => 'onEntityWritten',
        ];
    }

    public function onPreWriteValidation(PreWriteValidationEvent $event): void
    {
        try {
            if (!$this->shouldCapture($event->getContext())) {
                return;
            }

            $tracked = $this->config->getTrackedEntities();
            foreach ($event->getCommands() as $command) {
                if ($command instanceof ChangeSetAware && $command instanceof WriteCommand
                    && $this->graph->isRelevant($command->getEntityName(), $tracked)
                ) {
                    $command->requestChangeSet();
                }
            }
        } catch (\Throwable $e) {
            $this->logError('could not request change sets', $e);
        }
    }

    public function onEntityWritten(EntityWrittenContainerEvent $event): void
    {
        try {
            $context = $event->getContext();
            if (!$this->shouldCapture($context)) {
                return;
            }

            $tracked = $this->config->getTrackedEntities();
            if ($tracked === []) {
                return;
            }

            $commits = $this->buildCommits($event, $tracked);
            if ($commits === []) {
                return;
            }

            $this->writer->write($commits, $this->sourceResolver->resolve($context), $context);
        } catch (\Throwable $e) {
            $this->logError('could not capture write', $e);
        }
    }

    private function shouldCapture(Context $context): bool
    {
        return !$context->hasState(ChangeLogWriter::CONTEXT_STATE_SKIP)
            && $context->getVersionId() === Defaults::LIVE_VERSION
            && $this->config->isActive();
    }

    /**
     * @param list<string> $tracked
     *
     * @return list<ChangeLogCommit>
     */
    private function buildCommits(EntityWrittenContainerEvent $event, array $tracked): array
    {
        $builder = new CommitBuilder();
        foreach ($event->getEvents() ?? [] as $written) {
            if (!$written instanceof EntityWrittenEvent
                || \in_array($written->getEntityName(), self::SKIPPED_ENTITIES, true)
            ) {
                continue;
            }

            foreach ($written->getWriteResults() as $result) {
                $this->addResult($builder, $result, $tracked);
            }
        }

        return $builder->build();
    }

    /**
     * @param list<string> $tracked
     */
    private function addResult(CommitBuilder $builder, EntityWriteResult $result, array $tracked): void
    {
        $root = $this->rootResolver->resolve($result, $tracked);
        if ($root === null) {
            return;
        }

        $changes = $this->extractor->extract($result, $root->subEntity, $root->context);
        $before = $root->subEntity === null ? ($result->getChangeSet()?->getBefore(null) ?? []) : [];

        $builder->add($root, ChangeAction::fromOperation($result->getOperation()), $changes, $before);
    }

    private function logError(string $message, \Throwable $e): void
    {
        $this->logger->error(
            \sprintf('Admin change log: %s: %s', $message, $e->getMessage()),
            ['muwa', 'admin-change-log']
        );
    }
}
