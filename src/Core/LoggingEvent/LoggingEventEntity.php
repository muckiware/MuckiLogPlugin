<?php declare(strict_types=1);
/**
 * MuckiLogPlugin plugin
 *
 * @category   Muckiware
 * @package    Logger
 * @copyright  Copyright (c) 2021-2024 by muckiware
 *
 */
namespace MuckiLogPlugin\LoggingEvent;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class LoggingEventEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * vendor of plugin
     * @var string
     */
    protected string $vendor;

    protected string $plugin;

    protected string $loglevel;

    protected string $message;

    public function __construct()
    {

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

    public function getLoglevel(): string
    {
        return $this->loglevel;
    }

    public function setLoglevel(string $loglevel): void
    {
        $this->loglevel = $loglevel;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): void
    {
        $this->message = $message;
    }
}
