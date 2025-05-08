<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2025 by muckiware
 *
 */
namespace MuckiLogPlugin\Events;

class LoggerEvents {

    /**
     * @Event("MuckiLogPlugin\Events\CreateLogEvent")
     */
    final public const CREATE_LOG_EVENT_BEFORE = 'create.log.before';

    /**
     * @Event("MuckiLogPlugin\Events\CreateLogEvent")
     */
    final public const CREATE_LOG_EVENT_AFTER = 'create.log.after';
}
