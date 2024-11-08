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

enum ConfigPath: string
{
    case CONFIG_PATH_ACTIVE = 'MuckiLogPlugin.config.active';
    case CONFIG_PATH_LOG_LEVEL = 'MuckiLogPlugin.config.level';
    case CONFIG_PATH_MAX_BACKUP_INDEX = 'MuckiLogPlugin.config.maxbackupindex';
    case CONFIG_PATH_MAX_FILESIZE = 'MuckiLogPlugin.config.maxfilesize';
    case CONFIG_PATH_CONVERSIONPATTERN = 'MuckiLogPlugin.config.logpattern';
    case CONFIG_PATH_NOTIFICATION_MAIL_ADDRESS = 'MuckiLogPlugin.config.notificationMailAddress';
    case CONFIG_PATH_NOTIFICATION_MAIL_TEMPLATE_ID = 'MuckiLogPlugin.config.notificationMailTemplateId';
    case CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_DEBUG = 'MuckiLogPlugin.config.notificationMailActiveDebug';
    case CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_INFO = 'MuckiLogPlugin.config.notificationMailActiveInfo';
    case CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_WARNING = 'MuckiLogPlugin.config.notificationMailActiveWarning';
    case CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_ERROR = 'MuckiLogPlugin.config.notificationMailActiveError';
    case CONFIG_PATH_NOTIFICATION_MAIL_ACTIVE_CRITICAL = 'MuckiLogPlugin.config.notificationMailActiveCritical';

    case CONFIG_PATH_CORE_MAILER_SENDER = 'core.mailerSettings.senderAddress';
    case CONFIG_PATH_CORE_BASIC_EMAIL = 'core.basicInformation.email';

    case CONFIG_PATH_SALES_CHANNEL_ID = 'MuckiLogPlugin.config.salesChannelId';
    case CONFIG_PATH_SEND_MAIL_MODE = 'MuckiLogPlugin.config.sendMailMode';
    case CONFIG_PATH_ACTIVE_NOTIFICATION_MAIL = 'MuckiLogPlugin.config.activeNotificationMail';
}
