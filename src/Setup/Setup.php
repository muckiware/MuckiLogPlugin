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
use Symfony\Component\DependencyInjection\ContainerInterface;

use MuckiLogPlugin\Setup\EmailTemplate;

class Setup
{
    protected EmailTemplate $mailTemplateSetup;
    protected Connection $db;

    public function __construct(
        ?ContainerInterface $container,
        private readonly InstallContext $context,
    ) {
        if (!$container instanceof ContainerInterface) {
            throw new \Exception('Service container not available');
        }

        $this->mailTemplateSetup = new EmailTemplate($container);
    }
    public function install(): void
    {
        $this->mailTemplateSetup->createEmailTemplate($this->context->getContext());
    }

    public function uninstall(): void
    {
        $this->mailTemplateSetup->removeNotificationTemplateItems($this->context->getContext());
    }

    public function update(): void
    {
        $this->mailTemplateSetup->createEmailTemplate($this->context->getContext());
    }
}