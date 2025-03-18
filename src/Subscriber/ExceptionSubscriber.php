<?php declare(strict_types=1);
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
namespace MuckiLogPlugin\Subscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Shopware\Core\Framework\Adapter\Kernel\HttpKernel;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        protected LoggerInterface $logger
    ){}

    public static function getSubscribedEvents(): array {

        return [
            KernelEvents::EXCEPTION => 'onGeneralException',
//            KernelEvents::REQUEST => 'onGeneralRequest'
        ];
    }

    public function onGeneralException(RequestEvent $event): void
    {
        if (method_exists($event, 'getThrowable')) {

            /** @var \Throwable $exception */
            $exception = $event->getThrowable();

            $eventRequest = $event->getRequest();
            $requestUri = $eventRequest->getHttpHost().$eventRequest->attributes->get('sw-original-request-uri', $eventRequest->getRequestUri());
            $requestController = $eventRequest->attributes->get('_controller');

            $this->logger->error('PathInfo: '.$eventRequest->getPathInfo(), array('kernel', 'exceptions'));
            $this->logger->error('Request Uri: '.$requestUri, array('kernel', 'exceptions'));
            $this->logger->error('Request controller: '.$requestController, array('kernel', 'exceptions'));

            $this->logger->error('Error in file: '.$exception->getFile().':'.$exception->getLine(), array('kernel', 'exceptions'));
            $this->logger->error($exception->getMessage(), array('kernel', 'exceptions'));
            $this->logger->error($exception->getTraceAsString(), array('kernel', 'exceptions'));
        }
    }

    public function onGeneralRequest(RequestEvent $event): void
    {
//        $event->getKernel()->handle($event->getRequest(), HttpKernelInterface::MASTER_REQUEST, false);
    }
}
