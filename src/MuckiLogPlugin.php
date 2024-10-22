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
namespace MuckiLogPlugin;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;

use MuckiLogPlugin\Setup\Setup;

class MuckiLogPlugin extends Plugin
{
    /**
     * @throws \Exception
     */
    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);
        $setup = new Setup($this->container, $installContext);
        $setup->install($installContext);
    }

    /**
     * @throws \Exception
     */
    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }
        $setup = new Setup($this->container, $uninstallContext);
        $setup->uninstall($uninstallContext);
    }

    public function activate(ActivateContext $activateContext): void
    {
        // Activate entities, such as a new payment method
        // Or create new entities here, because now your plugin is installed and active for sure
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        // Deactivate entities, such as a new payment method
        // Or remove previously created entities
    }

    /**
     * @throws \Exception
     */
    public function update(UpdateContext $updateContext): void
    {
        $setup = new Setup($this->container, $updateContext);
        $setup->update($updateContext);
    }

    public function postInstall(InstallContext $installContext): void
    {
        //postInstall
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
        //postUpdate
    }
}