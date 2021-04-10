<?php 


declare(strict_types=1);

namespace MuckiLogPlugin\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use MuckiLogPlugin\Services\Settings;
use MuckiLogPlugin\Services\LogconfigInterface;
use MuckiLogPlugin\Logging\LoggerInterface;

class ResponseHeaderListener implements EventSubscriberInterface {

    /**
     *
     * @var Settings
     */
    protected $_settings;
    
    /**
     *
     * @var LogconfigInterface
     */
    protected $_logconfig;
    /**
     * 
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        LogconfigInterface $logconfig,
        Settings $settings,
        LoggerInterface $logger
    ) {
        $this->_logconfig = $logconfig;
        $this->_settings = $settings;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array {

        return [
            KernelEvents::RESPONSE => 'onResponse',
        ];
    }

    public function onResponse(ResponseEvent $event): void {
        
        if($this->checkRequest($event)) {

            $requestContent = [];
            
            try {
                $requestContent = json_decode($event->getRequest()->getContent(), true);
            } catch (\Exception $e) {
                $this->logger->errorItem('The JSON payload is malformed.');
                $this->logger->errorItem($e);
            }

            if(count($requestContent, COUNT_RECURSIVE) >= 1) {

                if($this->checkConfigItems($requestContent)) {
                    $this->_logconfig->removeLogConfigFiles(
                        $this->_settings->getLogConfigPath()
                    );
                }
            }
        }
    }
    
    protected function checkConfigItems(array $requestContent, string $configActive = Settings::CONFIG_PATH_ACTIVE): bool {
        
        if(array_search(1, array_column($requestContent, $configActive)) >= 0) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function checkRequest(ResponseEvent $event): bool {
        
        if($event->getResponse()->getStatusCode() < 200) {
            return false;
        }
        
        if($event->getRequest()->getMethod() !== 'POST') {
            return false;
        }
        
        $requestArray = explode('/', $event->getRequest()->getPathInfo(), 6);
        
        if(count($requestArray) === 6) {

            if($requestArray[1] !== 'api') {
                return false;
            }
            if($requestArray[4] !== 'system-config' && $requestArray[5] !== 'batch') {
                return  false;
            }
        } else {
            return false;
        }
        
        return true;
    }
}
