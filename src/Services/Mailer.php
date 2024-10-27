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

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\ParameterBag;

use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Core\Defaults as PluginDefaults;
use MuckiLogPlugin\Core\LoggingEvent\LoggingEventEntity;

class Mailer
{
    public function __construct(
        protected MailService $mailService,
        protected EntityRepository $templateRepository,
        protected SystemConfigService $systemConfigService,
        protected PluginSettings $pluginSettings,
        protected LoggerInterface $logger
    )
    {}

    public function sendMailNotification(LoggingEventEntity $logEvent, Context $context): bool
    {
        $data = $this->buildEmailParameter($logEvent, $context);
        $this->mailService->send(
            $data->all(),
            Context::createDefaultContext(),
            [
                'logEvent' => $logEvent
            ]
        );

//        $logEvent->getCreatedAt()
        return true;
    }

    private function getMailTemplate(string $mailTemplateId, Context $context): MailTemplateTypeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $mailTemplateId));
        $criteria->addAssociation('mailTemplates');

        $template = $this->templateRepository->search($criteria, $context);
        return $template->first();
    }

    private function buildEmailParameter(LoggingEventEntity $logEvent, Context $context): ParameterBag
    {
        $template = $this->getMailTemplate($logEvent->getNotificationEmailTemplateId(), $context);

        $data = new ParameterBag();
        $data->set('recipients', array($logEvent->getNotificationEmailReceiver() => $logEvent->getNotificationEmailReceiver()));
        $data->set('senderName',$logEvent->getNotificationEmailSender());
        $data->set('salesChannelId', $this->pluginSettings->getSalesChannelId());
        $data->set('contentHtml', $template->getMailTemplates()->first()->getContentHtml());
        $data->set('contentPlain', $template->getMailTemplates()->first()->getContentPlain());
        $data->set('subject', $template->getMailTemplates()->first()->getSubject());

        return $data;
    }
}
