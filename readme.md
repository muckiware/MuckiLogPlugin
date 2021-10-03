# MuckiLogPlugin
## Logger Plugin for Shopware 6 Webshops

This Shopware 6 Plugin creates log files by using php4log with file rotation, max file size setup, loglevels, etc. You can create for all your plugins own log fieles by context.
like /var/log/myloginplugin.vendor.log, or /var/log/myextraplugin.vendor.log

# How to use
To inject the logger class, you will need first the service configuration like this:

```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
  <services>
     <service id="YourPlugin\Storefront\Pagelet\Header\Subscriber\YourHeaderPageSubscriber">
       ...
       <argument type="service" id="MuckiLogPlugin\Logging\LoggerInterface"/>
         <tag name="kernel.event_subscriber"/>
     </service>
  </services>
</container>
```

Then inject the logger class into your Plugin

```php
<?php 
declare(strict_types=1);
namespace YourPlugin\Storefront\Pagelet\Header\Subscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MuckiLogPlugin\Logging\LoggerInterface;
/**
 * Class YourHeaderPageSubscriber
 */
class YourHeaderPageSubscriber implements EventSubscriberInterface {
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * YourHeaderPageSubscriber constructor.
     *
     * @param SystemConfigService $systemConfigService
     * @param EntityRepositoryInterface $mediaRepository
     */
    public function __construct(
        ...
        LoggerInterface $logger
    ) {
        ...
        $this->logger = $logger;
    }
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array {
        
        return [
            // Subscribing to HeaderPageLetLoadedEvent
            HeaderPageletLoadedEvent::class => 'HeaderPageletLoadedEvent',
        ];
    }
    /**
     * @param HeaderPageletLoadedEvent $event
     */
    public function HeaderPageletLoadedEvent(HeaderPageletLoadedEvent $event): void {
        $this->logger->debugItem('Call HeaderPageEvent', 'myplugin', 'vendor');
        ...
    }
}
```
## Use origin monolog interface
In order not to create dependencies from Muckilog to other plugins, the original Monolog interface can also be used. Muckilog plugin will replace the monolog method by using a decorator.
```xml
<?xml version="1.0" ?>
<container xmlns="http://symfony.com/schema/dic/services"
           xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd">
  <services>
     <service id="YourPlugin\Storefront\Pagelet\Header\Subscriber\YourHeaderPageSubscriber">
       ...
       <argument type="service" id="Psr\Log\LoggerInterface"/>
         <tag name="kernel.event_subscriber"/>
     </service>
  </services>
</container>
```

```php
<?php 
declare(strict_types=1);
namespace YourPlugin\Storefront\Pagelet\Header\Subscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Psr\Log\LoggerInterface;
/**
 * Class YourHeaderPageSubscriber
 */
class YourHeaderPageSubscriber implements EventSubscriberInterface {
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * YourHeaderPageSubscriber constructor.
     *
     * @param SystemConfigService $systemConfigService
     * @param EntityRepositoryInterface $mediaRepository
     */
    public function __construct(
        ...
        LoggerInterface $logger
    ) {
        ...
        $this->logger = $logger;
    }
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array {
        
        return [
            // Subscribing to HeaderPageLetLoadedEvent
            HeaderPageletLoadedEvent::class => 'HeaderPageletLoadedEvent',
        ];
    }
    /**
     * @param HeaderPageletLoadedEvent $event
     */
    public function HeaderPageletLoadedEvent(HeaderPageletLoadedEvent $event): void {
        $this->logger->debug('Call HeaderPageEvent', array('myplugin', 'vendor'));
        ...
        
        //OR call log with loglevel as parameter
        $this->logger->log('debug', 'Call HeaderPageEvent', array('myplugin', 'vendor'));
    }
}
```

## Loglevels
You have these kind of log levels

```php
$this->logger->traceItem('Call HeaderPageletLoadedEvent', 'myplugin', 'vendor');
$this->logger->debugItem('Call HeaderPageletLoadedEvent', 'myplugin', 'vendor');
$this->logger->infoItem('Call HeaderPageletLoadedEvent', 'myplugin', 'vendor');
$this->logger->warnItem('Call HeaderPageletLoadedEvent', 'myplugin', 'vendor');
$this->logger->errorItem('Call HeaderPageletLoadedEvent', 'myplugin', 'vendor');
$this->logger->fatalItem('Call HeaderPageletLoadedEvent', 'myplugin', 'vendor');
```