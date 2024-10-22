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

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Setup
{
    protected Connection $db;

    public function __construct(
        protected ?ContainerInterface $container,
        protected InstallContext $context,
    ) {
        if (!$container instanceof ContainerInterface) {
            throw new \Exception('Service container not available');
        }
    }
    public function install(InstallContext $installContext): void
    {
        $installMailTemplateSetup = new InstallEmailTemplate($this->container);
        $installMailTemplateSetup->createEmailTemplate($installContext->getContext());
    }

    public function uninstall(UninstallContext $installContext): void
    {
        $uninstallMailTemplateSetup = new UninstallEmailTemplate($this->container);
        $uninstallMailTemplateSetup->removeNotificationTemplateItems($installContext->getContext());
    }

    public function update(UpdateContext $installContext): void
    {
        $updateMailTemplateSetup = new UpdateEmailTemplate($this->container);
        $updateMailTemplateSetup->createEmailTemplate($installContext->getContext());
    }
}
