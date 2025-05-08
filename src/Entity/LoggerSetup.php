<?php declare(strict_types=1);
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
namespace MuckiLogPlugin\Entity;

use Shopware\Core\Framework\Uuid\Uuid;

use MuckiLogPlugin\Core\LogLevel;
class LoggerSetup
{
    protected string $id;
    /**
     * Name of plugin vendor
     * @var string
     */
    protected string $vendor;
    /**
     * Name of plugin
     * @var string
     */
    protected string $plugin;
    protected LogLevel $logLevel;
    protected string $message;
    protected ?string $notificationEmailTemplateId;
    protected ?string $notificationEmailReceiver;
    protected ?string $notificationEmailSender;

    protected bool $sendNotification;

    protected string $pathLogFile;

    public function __construct()
    {
        $this->id = Uuid::randomHex();
        $this->logLevel = LogLevel::DEBUG;
        $this->sendNotification = false;
        $this->notificationEmailTemplateId = null;
        $this->notificationEmailReceiver = null;
        $this->notificationEmailSender = null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function setVendor(string $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function getPlugin(): string
    {
        return $this->plugin;
    }

    public function setPlugin(string $plugin): void
    {
        $this->plugin = $plugin;
    }

    public function getLogLevel(): LogLevel
    {
        return $this->logLevel;
    }

    public function setLogLevel(LogLevel $logLevel): void
    {
        $this->logLevel = $logLevel;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }

    public function getNotificationEmailTemplateId(): ?string
    {
        return $this->notificationEmailTemplateId;
    }

    public function setNotificationEmailTemplateId(string $notificationEmailTemplateId): void
    {
        $this->notificationEmailTemplateId = $notificationEmailTemplateId;
    }

    public function getNotificationEmailReceiver(): ?string
    {
        return $this->notificationEmailReceiver;
    }

    public function setNotificationEmailReceiver(string $notificationEmailReceiver): void
    {
        $this->notificationEmailReceiver = $notificationEmailReceiver;
    }

    public function getNotificationEmailSender(): ?string
    {
        return $this->notificationEmailSender;
    }

    public function setNotificationEmailSender(?string $notificationEmailSender): void
    {
        $this->notificationEmailSender = $notificationEmailSender;
    }

    public function isSendNotification(): bool
    {
        return $this->sendNotification;
    }

    public function setSendNotification(bool $sendNotification): void
    {
        $this->sendNotification = $sendNotification;
    }

    public function getPathLogFile(): string
    {
        return $this->pathLogFile;
    }
    public function setPathLogFile(string $pathLogFile): void
    {
        $this->pathLogFile = $pathLogFile;
    }
}
