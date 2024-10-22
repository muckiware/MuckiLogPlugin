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
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\ParameterBag;

use LightsOnCustomizeSpecials\Service\Settings as PluginSettings;
use LightsOnCustomizeSpecials\Core\Defaults as PluginDefaults;
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

    public function sendNotification(array $emails, OrderEntity $order, Context $context): bool
    {
        foreach ($emails as $email) {

            $data = $this->buildEmailParameter([$email], $order, $context);
            $this->mailService->send(
                $data->all(),
                Context::createDefaultContext(),
                [
                    'thirdPartySupplierEMail' => $email,
                    'order' => $order,
                    'salesChannel' => $order->getSalesChannel(),
                ]
            );
        }

        return true;
    }

    private function getMailTemplate(Context $context)
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', $this->pluginSettings->mailTemplateId()));
        $criteria->addAssociation('mailTemplates');

        $template = $this->templateRepository->search($criteria, $context);

        if ($template->count() !== 0) {
            $templates = $template->first()->getMailTemplates();
            if ($templates->count()) {
                foreach ($templates as $mailTemplate) {
                    if ($mailTemplate->getDescription() == PluginDefaults::MAIL_TEMPLATE_DESC) {
                        return $mailTemplate;
                    }
                }
            }
        } else {
            $this->logger->warning('Missing valid mail template', array('lion', 'customizeSpecials'));
        }

        return null;
    }

    private function buildEmailParameter(array $emails, OrderEntity $order, Context $context): ParameterBag
    {
        $template = $this->getMailTemplate($context);
        $data = new ParameterBag();
        $recipientsArray = [];
        foreach ($emails as $recipient) {
            $recipientsArray[$recipient] = $recipient;
        }
        $data->set('recipients', $recipientsArray);
        $data->set(
            'senderName',
            $this->systemConfigService->get('core.basicInformation.email', $order->getSalesChannel())
        );
        $data->set('salesChannelId', $order->getSalesChannelId());
        $data->set('contentHtml', $template->getContentHtml());
        $data->set('contentPlain', $template->getContentPlain());
        $data->set('subject', $template->getSubject());

        return $data;
    }
}
