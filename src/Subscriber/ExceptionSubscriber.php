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
            KernelEvents::EXCEPTION => 'onGeneralException'
        ];
    }

    public function onGeneralException(RequestEvent $event): void
    {
        if (method_exists($event, 'getThrowable')) {

            /** @var \Throwable $exception */
            $exception = $event->getThrowable();
            $this->logger->error('Error i file: '.$exception->getFile().':'.$exception->getLine(), array('sw', 'exceptions'));
            $this->logger->error($exception->getMessage(), array('sw', 'exceptions'));
            $this->logger->error($exception->getTraceAsString(), array('sw', 'exceptions'));
        }

    }
}
