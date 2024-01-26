<?php
/**
 * MuckiLogPlugin plugin
 *
 *
 * @category   Muckiware
 * @package    Muckilog
 * @copyright  Copyright (c) 2021-2024 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

declare(strict_types=1);

namespace MuckiLogPlugin\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use MuckiLogPlugin\Services\Settings as PluginSettings;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Logging\LoggerInterface;

class ResponseHeaderListener implements EventSubscriberInterface
{
    /**
     *
     * @var PluginSettings
     */
    protected PluginSettings $pluginSettings;

    /**
     *
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var LogconfigInterface
     */
    protected LogconfigInterface $logConfig;

    public function __construct(
        LogconfigInterface $logConfig,
        PluginSettings $pluginSettings,
        LoggerInterface $logger
    ) {
        $this->logConfig = $logConfig;
        $this->pluginSettings = $pluginSettings;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array {

        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if($this->checkRequest($event)) {

            $requestContent = [];

            try {
                $requestContent = json_decode($event->getRequest()->getContent(), true);
            } catch (\Exception $e) {
                $this->logger->errorItem('The JSON payload is malformed.');
                $this->logger->errorItem($e->getMessage());
            }

            if(
                count($requestContent, COUNT_RECURSIVE) >= 1 &&
                $this->checkConfigItems($requestContent)
            ) {
                $this->logConfig->removeLogConfigFiles($this->pluginSettings->getLogConfigPath());
            }
        }
    }
    
    protected function checkConfigItems(array $requestContent): bool
    {
        $configActive = PluginSettings::CONFIG_PATH_ACTIVE;
        if(array_search(1, array_column($requestContent, $configActive)) >= 0) {
            return true;
        }
        return false;
    }
    
    protected function checkRequest(ResponseEvent $event): bool
    {
        if(
            $event->getResponse()->getStatusCode() < 200 ||
            ($event->getRequest()->getMethod() !== 'POST')
        ) {
            return false;
        }
        
        $requestArray = explode('/', $event->getRequest()->getPathInfo(), 6);

        if(
            in_array('api', $requestArray) &&
            (in_array('system-config', $requestArray) ||
            in_array('batch', $requestArray))
        ) {
            return true;
        }

        return false;
    }
}
