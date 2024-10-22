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
namespace MuckiLogPlugin\Setup;

use Shopware\Core\Framework\Context;

use MuckiLogPlugin\Core\Defaults as PluginDefaults;

class UninstallEmailTemplate extends EmailTemplate
{
    public function removeNotificationTemplateItems(Context $context): void
    {
        $mailTemplateTypeId = $this->getMailTemplateTypeIdByTechnicalName(
            PluginDefaults::EMAIL_TEMPLATE_TECHNICAL_NAME,
            $context
        );
        if($mailTemplateTypeId) {

            $this->removeEmailTemplate($mailTemplateTypeId, $context);
            $this->removeEmailTemplateType($mailTemplateTypeId, $context);
        }
    }

    public function removeEmailTemplateType(string $mailTemplateTypeId, Context $context): void
    {
        $this->mailTemplateTypeRepository->delete(
            array_values(array(array('id' => $mailTemplateTypeId))), $context
        );
    }

    public function removeEmailTemplate(string $mailTemplateTypeId, Context $context): void
    {
        $templateIds = $this->getMailTemplateIdsByTemplateTypeId($mailTemplateTypeId, $context);
        if(!empty($templateIds)) {

            $templateIds = array_map(
                static fn ($id) => [ 'id' => $id ],
                $templateIds,
            );
            $this->mailTemplateRepository->delete(array_values($templateIds), $context);
        }
    }
}
