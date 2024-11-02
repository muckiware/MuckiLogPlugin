# MuckiLogPlugin
## Logger Plugin for Shopware 6 Webshops

This Shopware 6 Plugin creates log files with file rotation, max file size setup, loglevels, etc. You can create for all your plugins own log fieles by context.
like /var/log/myloginplugin.vendor.log, or /var/log/myextraplugin.vendor.log
It can send emails by log events. For example: You get an error log event, and it can sends an email to a specific address.

## Installation
```shell
composer require muckiware/log-plugin
bin/console plugin:install -a MuckiLogPlugin
```

## How to use
In order not to create dependencies from Muckilog to other plugins, the original Monolog interface can be used. Muckilog plugin will replace the monolog method by using a decorator.
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
class YourHeaderPageSubscriber implements EventSubscriberInterface
{

    public function __construct(
        ...
        protected LoggerInterface $logger
    ) {
        ...
        $this->logger = $logger;
    }
    /**
     * @return array
     */
    public static function getSubscribedEvents(): array
    {
        return [
            // Subscribing to HeaderPageLetLoadedEvent
            HeaderPageletLoadedEvent::class => 'HeaderPageletLoadedEvent',
        ];
    }
    /**
     * @param HeaderPageletLoadedEvent $event
     */
    public function HeaderPageletLoadedEvent(HeaderPageletLoadedEvent $event): void
    {
        $this->logger->debug('Call HeaderPageEvent', array('myplugin', 'vendor'));
        ...
 
        $this->logger->info('debug', 'Call HeaderPageEvent', array('myplugin', 'vendor'));
    }
}
```

## Loglevels
You have these kind of log levels

```php

//Regular logger usage
$this->logger->debug('Call HeaderPageletLoadedEvent', array('myplugin', 'vendor'));
$this->logger->info('Call HeaderPageletLoadedEvent', array('myplugin', 'vendor'));
$this->logger->warning('Call HeaderPageletLoadedEvent', array('myplugin', 'vendor'));
$this->logger->error('Call HeaderPageletLoadedEvent', array('myplugin', 'vendor'));
$this->logger->critical('Call HeaderPageletLoadedEvent', array('myplugin', 'vendor'));

//logger with context setup object usage
$loggerContext = array(
    'myplugin',
    'vendor',
    array('setup' =>
        array(
            'notificationEmail' => true,
            'notificationEmailReceiver' => 'notificationEmailReceiver@example.com',
            'notificationEmailSender' => 'notificationEmailSender@example.com',
        )
    )
);
$this->logger->critical('Call HeaderPageletLoadedEvent with mail notification', $loggerContext);
```
## CLIs
```shell
bin/console muckiware:logger:send
```
This command execute the sending of open logger events by email. Regular will this execution runs by Shopware schedules all 600 seconds.
```shell
bin/console muckiware:logger:check
```
This command is just for testing logging methods.

# Testing
## phpstan
### Install
Install phpstan, if required
```shell
cd custom/plugin/MuckiLogPlugin
composer install
```
### Execute
```shell
cd custom/plugins/MuckiLogPlugin 
composer run-script phpstan
```
## Unit test
### Execute first time
```shell
./vendor/bin/phpunit --configuration="custom/plugins/MuckiLogPlugin" --testsuite "migration"
```

### Execute regular run
```shell
./vendor/bin/phpunit --configuration="custom/plugins/MuckiLogPlugin"
```