# MuckiLogPlugin
Logger Plugin for Shopware 6 Webshops

This Shopware 6 Plugin creates log files by using php4log with file rotation and max file size setup. You can create for all your plugins own log fieles.

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
			<argument type="service" id="MuckiLogPlugin\Logging\Logger"/>
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
use MuckiLogPlugin\Logging\Logger;

/**
 * Class YourHeaderPageSubscriber
 */
class YourHeaderPageSubscriber implements EventSubscriberInterface {

    /**
     * @var Logger
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
        Logger $logger
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

        $this->logger->debugItem('Call HeaderPageEvent', 'myplugin', 'header');
        ...
    }
}
```
