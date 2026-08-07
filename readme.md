# MuckiLogPlugin
## Logger Plugin for Shopware 6 Webshops

This Shopware 6 Plugin creates log files with file rotation, max file size setup, loglevels, etc. You can create for all your plugins own log fieles by context.
like /var/log/myloginplugin.vendor.log, or /var/log/myextraplugin.vendor.log
It can send emails by log events. For example: You get an error log event, and it can sends an email to a specific address.
## Features overview
- Works for alle Shopware 6 versions, 6.5.x or greater
- Create log files with automatic file rotation by max file size and max file count
- Log files can automatically compress by gzip
- Easy to use logger interface, which can be used in all plugins and creates own log files by context
- Global log setup via plugin configuration, like log level, pattern for log content format, etc.
  ![backup_paths_config.png](img%2Fglobal_plugin_setup.png)
- Can send emails by log events
- Storefront errors can be idendified by a unique error id, which can be used to find the error in the log file
  ![backup_paths_config.png](img%2Fstorefront_error_message.png)
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
## Decorator Coverage

MuckiLogPlugin intercepts Shopware and Symfony log calls through two complementary mechanisms.

### 1. `Psr\Log\LoggerInterface` — via service decorator

The primary decorator (`LoggerServiceDecorator`) wraps the default Symfony/Monolog logger. Any service
that injects the logger via the standard PSR-3 interface is automatically covered:

```xml
<argument type="service" id="Psr\Log\LoggerInterface"/>
```

This is the recommended way to use the logger in your own plugins.

### 2. `id="logger"` — via DI CompilerPass

Many Shopware core services use the explicit `logger` service ID instead of the interface alias:

```xml
<argument type="service" id="logger"/>
```

The `LoggerDecoratorCompilerPass` runs at container compile time, traverses all service definitions
and replaces every `id="logger"` reference with a reference to the `LoggerServiceDecorator` — except
for services that are transitive dependencies of the decorator itself (those would create circular
references).

**What this means in practice:** Log calls from Shopware core services like `ProductStreamProcessor`,
`StockUpdater`, or `ElasticsearchEntitySearcher` are routed through MuckiLogPlugin automatically,
without any code changes in those services.

### Why Monolog channel loggers (`id="monolog.logger.*"`) are not auto-decorated

Shopware registers a dedicated Monolog logger per concern (`monolog.logger.event`,
`monolog.logger.messenger`, `monolog.logger.request`, etc.). Decorating these channels with a
service decorator is architecturally incompatible with the current plugin design:

`LoggerServiceDecorator` → `MuckiLogPlugin\Logging\Logger` → `LoggingEvent`
→ `muwa_logging_event.repository` → Shopware DAL → most Shopware services
→ those Shopware services use channel loggers → **circular reference**

The correct approach for intercepting channel loggers is a **Monolog Handler** (a handler is a leaf
node in the DI graph and does not create cycles). This is documented as a future improvement.

### Context array convention

The decorator reads the PSR-3 context array to determine which log file to write to:

```php
// Writes to var/log/myplugin.myvendor.log
$this->logger->error('Something failed', ['myvendor', 'myplugin']);

// Writes to var/log/dev.sw.log (default fallback)
$this->logger->error('Something failed', []);

// Writes with email notification
$this->logger->error('Critical failure', [
    'myvendor',
    'myplugin',
    ['setup' => [
        'notificationEmail'         => true,
        'notificationEmailReceiver' => 'ops@example.com',
        'notificationEmailSender'   => 'shop@example.com',
    ]]
]);
```

> **Important:** The context array must use numeric indices `0` (vendor) and `1` (plugin name).
> Associative arrays passed by Shopware core services (e.g. `['exception' => ...]`) are handled
> gracefully — they fall back to the default `sw/dev` log file without errors.

## CLIs
```shell
bin/console muckiware:logger:send
```
This command execute the sending of open logger events by email. Regular will this execution runs by Shopware schedules all 600 seconds.
```shell
bin/console muckiware:logger:check
```
This command tests the `Psr\Log\LoggerInterface` injection path and writes sample log entries
across all log levels.

```shell
bin/console muckiware:logger:check-sw
```
This command tests the `id="logger"` injection path (typical Shopware core pattern) to verify
that the `LoggerDecoratorCompilerPass` correctly routes those calls through MuckiLogPlugin.
The first output line shows the actual class injected — it should read
`MuckiLogPlugin\Services\LoggerServiceDecorator`, not `Monolog\Logger`.

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