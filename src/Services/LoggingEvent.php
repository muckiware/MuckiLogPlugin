<?php
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\Services;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

class LoggingEvent
{
    public function __construct(
        protected EntityRepository $loggingEventRepository
    )
    {}

    public function saveEvent(string $loglevel, string $vendor, string $plugin, string $message): void
    {
        $this->loggingEventRepository->create([
            [
                'id' => Uuid::randomHex(),
                'vendor' => $vendor,
                'plugin' => $plugin,
                'loglevel' => $loglevel,
                'message' => $message,
                'created_at' => new \DateTime('now', new \DateTimeZone('UTC'))
            ],
        ], Context::createDefaultContext());
    }
}