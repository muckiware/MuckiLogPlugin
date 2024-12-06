<?php
/**
 * MuckiLogPlugin
 *
 * @category   SW6 Plugin
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace MuckiLogPlugin\Core;

class Defaults
{
    public const EMAIL_TEMPLATE_TECHNICAL_NAME = 'muwa_log_notification';
    public const EMAIL_TEMPLATE_TYPE_NAME = 'Log Notification';
    public const EMAIL_TEMPLATE_DESC = 'Template for log notification events';
    public const EMAIL_TEMPLATE_SUBJECT = 'Log Notification';

    const CONTEXT = 'acme';
    const EXTENSION = 'loggerCheck';
    const EXTENSION_SW = 'loggerCheckSw';

    const DEFAULT_SW_CONTEXT = 'sw';
    const DEFAULT_SW_EXTENSION = 'dev';

    const DEFAULT_SEND_MAIL_MODE = 'eachLogLevelOneMail';
    public const DEFAULT_TASK_INTERVAL_IN_SECONDS = 300;

    const LOGGER_PATH = '/Log4php/Logger.php';
    const LOG_PATH = '/var/log';
    const LOGGER_CONFIG_PATH = '/Resources/config';
}