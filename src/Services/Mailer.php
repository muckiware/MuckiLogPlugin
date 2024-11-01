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
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Symfony\Component\HttpFoundation\ParameterBag;

use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Core\Defaults as PluginDefaults;
use MuckiLogPlugin\Core\LoggingEvent\LoggingEventEntity;
use MuckiLogPlugin\Core\EmailNotification\EmailNotificationEntity;
use MuckiLogPlugin\Core\SendMailMode;

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

    public function sendMailNotification(EmailNotificationEntity $emailNotification): void
    {
        $this->mailService->send(
            $emailNotification->getEmailParameter()->all(),
            $emailNotification->getContext(),
            [
                'logEvents' => $emailNotification->getLoggingEventCollection()->getElements()
            ]
        );
    }

    private function getMailTemplate(string $mailTemplateId, Context $context): ?MailTemplateTypeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $mailTemplateId));
        $criteria->addAssociation('mailTemplates');

        $template = $this->templateRepository->search($criteria, $context);
        if($template->first()) {

            /** @var MailTemplateTypeEntity $mailTemplateTypeEntity */
            $mailTemplateTypeEntity = $template->first();
            return $mailTemplateTypeEntity;
        }
        return null;
    }

    public function buildEmailParameterByEvent(LoggingEventEntity $logEvent, Context $context): ?ParameterBag
    {
        $template = $this->getMailTemplate($logEvent->getNotificationEmailTemplateId(), $context);
        if($template) {

            /** @var MailTemplateCollection $mailTemplates */
            $mailTemplates = $template->getMailTemplates();
            if($mailTemplates && $mailTemplates->first()) {

                $data = new ParameterBag();
                $data->set('recipients', array($logEvent->getNotificationEmailReceiver() => $logEvent->getNotificationEmailReceiver()));
                $data->set('senderName',$logEvent->getNotificationEmailSender());
                $data->set('salesChannelId', $this->pluginSettings->getSalesChannelId());
                $data->set('contentHtml', $mailTemplates->first()->getContentHtml());
                $data->set('contentPlain', $mailTemplates->first()->getContentPlain());
                $data->set('subject', $mailTemplates->first()->getSubject());

                return $data;
            }
        }

        return null;
    }

    public function buildEmailParameterByLoglevel(Context $context): ?ParameterBag
    {
        $template = $this->getMailTemplate($this->pluginSettings->getNotificationMailTemplateId(), $context);
        if($template) {

            /** @var MailTemplateCollection $mailTemplates */
            $mailTemplates = $template->getMailTemplates();
            if($mailTemplates && $mailTemplates->first()) {

                $notificationEmailReceiver = $this->pluginSettings->getNotificationMailAddress();
                $data = new ParameterBag();
                $data->set('recipients', array($notificationEmailReceiver => $notificationEmailReceiver));
                $data->set('senderName',$this->pluginSettings->getNotificationMailSender());
                $data->set('salesChannelId', $this->pluginSettings->getSalesChannelId());
                $data->set('contentHtml', $mailTemplates->first()->getContentHtml());
                $data->set('contentPlain', $mailTemplates->first()->getContentPlain());
                $data->set('subject', $mailTemplates->first()->getSubject());

                return $data;
            }
        }

        return null;
    }
}
