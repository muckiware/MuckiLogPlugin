<?php 


declare(strict_types=1);

namespace MuckiLogPlugin\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use MuckiLogPlugin\Logging\Logger;

class ResponseHeaderListener implements EventSubscriberInterface {

    /**
     * 
     * @var Logger
     */
    protected $logger;

    public function __construct(Logger $logger) {
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
                    $this->logger->debugItem('Remove config files');
                } else {
                    $this->logger->debugItem('Nothing to do');
                }
            }
        }
    }
    
    protected function checkConfigItems(array $requestContent): bool {
        
        if(array_search(1, array_column($requestContent, 'MuckiLogPlugin.config.active')) >= 0) {
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