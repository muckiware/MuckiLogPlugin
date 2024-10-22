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
namespace MuckiLogPlugin\Setup;

use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateType\MailTemplateTypeEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateCollection;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;

use MuckiLogPlugin\Core\Defaults as PluginDefaults;
class EmailTemplate
{
    protected EntityRepository $mailTemplateTypeRepository;

    protected EntityRepository $mailTemplateRepository;

    public function __construct(
        ContainerInterface $container,
    ) {
        /** @var EntityRepository mailTemplateTypeRepository */
        $this->mailTemplateTypeRepository = $container->get('mail_template_type.repository');

        /** @var EntityRepository mailTemplateRepository */
        $this->mailTemplateRepository = $container->get('mail_template.repository');
    }

    public function getMailTemplateTypeIdByTechnicalName(string $technicalName, Context $context): ?string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $technicalName));

        $mailTemplateTypeId = $this->mailTemplateTypeRepository->searchIds($criteria, $context);
        if(!empty($mailTemplateTypeId->getIds())) {
            return $mailTemplateTypeId->firstId();
        }
        return null;
    }
    public function getMailTemplateIdsByTemplateTypeId(string $mailTemplateTypeId, Context $context): ?array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mailTemplateTypeId', $mailTemplateTypeId));

        $mailTemplateTypeIds = $this->mailTemplateRepository->searchIds($criteria, $context);
        if(!empty($mailTemplateTypeIds->getIds())) {
            return $mailTemplateTypeIds->getIds();
        }
        return null;
    }

    public function createEmailTemplate(Context $context): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeId($context);
        if($mailTemplateTypeId['isNew']) {

            $this->mailTemplateTypeRepository->create(array([
                'id' => $mailTemplateTypeId['id'],
                'name' => PluginDefaults::EMAIL_TEMPLATE_TYPE_NAME,
                'technicalName' => PluginDefaults::EMAIL_TEMPLATE_TECHNICAL_NAME,
                'availableEntities' => [
                    'salesChannel' => 'sales_channel',
                ],
            ]), $context);
        }

        $templateIds = $this->getMailTemplateIdsByTemplateTypeId($mailTemplateTypeId['id'], $context);
        if(empty($templateIds)) {

            $this->mailTemplateRepository->create(array([
                'id' => Uuid::randomHex(),
                'mailTemplateTypeId' => $mailTemplateTypeId['id'],
                'subject' => PluginDefaults::EMAIL_TEMPLATE_SUBJECT,
                'contentPlain' => $this->loadContentByType('plain.html.twig'),
                'contentHtml' => $this->loadContentByType('html.html.twig'),
                'senderName' => '{{ salesChannel.name }}',
                'description' => PluginDefaults::EMAIL_TEMPLATE_DESC,
                'translations' => [],
            ]), $context);
        }
    }

    public function getMailTemplateTypeId(Context $context): array
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeIdByTechnicalName(
            PluginDefaults::EMAIL_TEMPLATE_TECHNICAL_NAME,
            $context
        );
        if($mailTemplateTypeId) {
            return array(
                'id' => $mailTemplateTypeId,
                'isNew' => false
            );
        }
        return array(
            'id' => Uuid::randomHex(),
            'isNew' => true
        );
    }

    public function loadContentByType(string $type): string
    {
        $filename = __DIR__.'/EmailTemplates/'.$type;

        if (is_file($filename)) {
            return file_get_contents($filename);
        }

        return 'Missing template content';
    }
}
