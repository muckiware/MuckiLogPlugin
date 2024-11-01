<?php

namespace MuckiLogPlugin\Core\EmailNotification;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Symfony\Component\HttpFoundation\ParameterBag;
use Shopware\Core\Framework\Context;

use MuckiLogPlugin\Core\LoggingEvent\LoggingEventCollection;

class EmailNotificationEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    protected string $loglevel;
    protected ParameterBag $emailParameter;

    protected Context $context;

    protected LoggingEventCollection $loggingEventCollection;

    public function getEmailParameter(): ParameterBag
    {
        return $this->emailParameter;
    }

    public function setEmailParameter(ParameterBag $emailParameter): void
    {
        $this->emailParameter = $emailParameter;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function setContext(Context $context): void
    {
        $this->context = $context;
    }

    public function getLoggingEventCollection(): LoggingEventCollection
    {
        return $this->loggingEventCollection;
    }

    public function setLoggingEventCollection(LoggingEventCollection $loggingEventCollection): void
    {
        $this->loggingEventCollection = $loggingEventCollection;
    }

    public function getLoglevel(): string
    {
        return $this->loglevel;
    }

    public function setLoglevel(string $loglevel): void
    {
        $this->loglevel = $loglevel;
    }
}