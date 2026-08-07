# MuckiLogPlugin – CLAUDE.md

## Zweck

`muckiware/log-plugin` (v2.4.0) ersetzt das Standard-Monolog-Logging von Shopware durch **Apache Log4php** mittels des Symfony **Decorator-Patterns**. Jede Log-Ausgabe, die in Shopware oder Plugins über `Psr\Log\LoggerInterface` erfolgt, wird automatisch abgefangen und durch Log4php geroutet — ohne Codeänderungen in anderen Plugins.

---

## Decorator-Mechanismus (Kernprinzip)

### 1. Logger-Decorator

**Datei:** `src/Services/LoggerServiceDecorator.php`
**Interface:** implementiert `Psr\Log\LoggerInterface`

**Registrierung in** `src/Resources/config/services.xml`:

```xml
<service id="MuckiLogPlugin\Services\LoggerServiceDecorator"
         decorates="Psr\Log\LoggerInterface"
         decoration-priority="10">
    <argument type="service" id="MuckiLogPlugin\Services\LoggerServiceDecorator.inner" />
    <argument type="service" id="MuckiLogPlugin\Logging\LoggerInterface"/>
    <argument type="service" id="MuckiLogPlugin\Services\SettingsInterface"/>
    <argument type="service" id="MuckiLogPlugin\Services\Helper"/>
</service>
```

**Wie es funktioniert:**
- `decorates="Psr\Log\LoggerInterface"` weist Symfony an, diesen Service als Wrapper um den Original-Logger (Monolog) einzusetzen.
- Der Original-Monolog-Service wird als `LoggerServiceDecorator.inner` übergeben, aber **nicht mehr aufgerufen** — alle Log-Methoden (`emergency`, `alert`, `critical`, `error`, `warning`, `notice`, `info`, `debug`, `log`) delegieren stattdessen an `MuckiLogPlugin\Logging\Logger` (Log4php).
- `decoration-priority="10"` — höhere Zahl = äußerster Decorator (wird zuerst ausgeführt).

### 2. ErrorController-Decorator

**Datei:** `src/Storefront/Controller/ErrorControllerDecorator.php`

```xml
<service id="MuckiLogPlugin\Storefront\Controller\ErrorControllerDecorator"
         decorates="Shopware\Storefront\Controller\ErrorController"
         decoration-priority="100">
```

Dekoriert den Shopware `ErrorController`, um Storefront-Exceptions (außer 404) automatisch mit Log4php zu loggen und eine Flash-Message mit Error-ID anzuzeigen.

---

## Log4php-Integration

### Logging-Stack

```
Psr\Log\LoggerInterface (Aufruf aus beliebigem Plugin/Shopware)
  └─> LoggerServiceDecorator::error/warning/info/debug(...)
        └─> LoggerServiceDecorator::getLoggerSetup() → LoggerSetup DTO
              └─> MuckiLogPlugin\Logging\Logger::logItem()
                    └─> Logconfig::checkConfigPath() — lädt/erstellt XML-Konfiguration
                          └─> Log4php\Logger::error/warning/info/debug()
                                └─> RollingFileAppender → var/log/<plugin>.<vendor>.log
```

### Log-Konfiguration pro Plugin

`Logconfig` erzeugt automatisch eine XML-Konfigurationsdatei pro Vendor/Plugin-Kombination:

- **Pfad-Konvention:** `src/Resources/config/logconfig.<plugin>.<vendor>.xml`
- **Log-Datei:** `var/log/<plugin>.<vendor>.log`
- Standard-Appender: `LoggerAppenderRollingFile` mit konfigurierbarer Dateigröße, Backup-Anzahl und Komprimierung.

---

## Context-Array-Konvention

Damit ein Log-Eintrag der richtigen Log-Datei zugeordnet wird, muss der Context-Array eine bestimmte Struktur haben:

```php
$this->logger->error('Fehlermeldung', [
    'vendorName',    // $context[0] → Vendor-Ordner / Log-Kategorie
    'pluginName',    // $context[1] → Plugin-Name / Dateiname
    // optional:
    [
        'setup' => [
            'notificationEmail'           => true,
            'notificationEmailReceiver'   => 'admin@example.com',
            'notificationEmailSender'     => 'shop@example.com',
            'notificationEmailTemplateId' => '<uuid>',
        ]
    ]
]);
```

Fehlt der Context, wird der Fallback `sw` / `dev` verwendet → `var/log/dev.sw.log`.

---

## Services im Überblick

| Service | Zweck |
|---|---|
| `LoggerServiceDecorator` | Decorator für `Psr\Log\LoggerInterface`, Einstiegspunkt für alle Logs |
| `MuckiLogPlugin\Logging\Logger` | Routing an Log4php, dispatcht Events vor/nach dem Logging |
| `Logconfig` | Verwaltet Log4php XML-Config-Dateien (erstellt sie bei Bedarf) |
| `Settings` | Liest Plugin-Konfiguration aus `SystemConfigService` |
| `LoggingEvent` | Speichert Benachrichtigungs-Events in DB-Tabelle `muwa_logging_event` |
| `SendNotification` | Versendet E-Mail-Benachrichtigungen per Log-Level |
| `Mailer` | Shopware `MailService`-Wrapper für Notification-Mails |
| `ErrorControllerDecorator` | Decorator für Storefront `ErrorController` |

---

## Events

| Event-Konstante | Wann |
|---|---|
| `LoggerEvents::CREATE_LOG_EVENT_BEFORE` (`create.log.before`) | Vor dem eigentlichen Log-Schreiben |
| `LoggerEvents::CREATE_LOG_EVENT_AFTER` (`create.log.after`) | Nach dem Log-Schreiben (inkl. DB-Speicherung) |

Payload: `CreateLogEvent` mit `LoggerSetup`-DTO (enthält Vendor, Plugin, LogLevel, Message, Notification-Einstellungen).

---

## Entities & Migrations

| Entity | DB-Tabelle | Zweck |
|---|---|---|
| `LoggingEventEntity` | `muwa_logging_event` | Speichert Log-Einträge, für die eine E-Mail-Benachrichtigung aussteht |
| `EmailNotificationEntity` | — | E-Mail-Notification-Konfiguration |

Migration: `src/Migration/Migration1729614227.php`

---

## Plugin-Konfiguration (`config.xml`)

Alle Keys beginnen mit `MuckiLogPlugin.config.`:

| Key | Typ | Bedeutung |
|---|---|---|
| `active` | bool | Plugin aktiv/inaktiv |
| `level` | string | Mindest-Log-Level (`debug`, `info`, `warning`, `error`, `critical`) |
| `maxbackupindex` | int | Anzahl Rolling-Backup-Dateien (Default: 10) |
| `maxfilesize` | int | Max. Dateigröße in MB (Default: 10) |
| `logpattern` | string | Log4php Conversion-Pattern |
| `activeCompress` | bool | Backup-Logs komprimieren |
| `activeNotificationMail` | bool | E-Mail-Benachrichtigung aktiv |
| `notificationMailAddress` | string | Empfänger-Adresse |
| `notificationMailTemplateId` | uuid | Shopware E-Mail-Template |
| `notificationMailActive{Level}` | bool | Benachrichtigung je Log-Level |
| `salesChannelId` | uuid | Sales-Channel für Mails |
| `sendMailMode` | string | `eachLogLevelOneMail` (Default) |

---

## Scheduled Task

`SendNotificationLogsTask` — läuft alle 300 Sekunden, versendet ausstehende Log-Benachrichtigungen aus `muwa_logging_event`.

---

## CLI-Befehle

| Befehl | Klasse | Zweck |
|---|---|---|
| (registriert) | `Commands\Checkup` | Logger-Konfiguration prüfen |
| (registriert) | `Commands\SendNotification` | Benachrichtigungen manuell auslösen |

---

## Wichtige Besonderheiten / Fallstricke

- **Kein Monolog-Fallback**: Der Original-Monolog-Service (`.inner`) wird im Decorator gespeichert, aber **nie aufgerufen**. Wenn das Plugin deaktiviert ist (`Settings::isEnabled() === false`), wird **gar nicht** geloggt.
- **Level-Mapping**: `alert` → `WARNING`, `notice` → `INFO`, `log()` → immer `DEBUG` — Abweichung von PSR-3-Semantik beachten.
- **Context-Index-Abhängigkeit**: Der Context-Array wird per numerischem Index (`$context[0]`, `$context[1]`, `$context[2]`) ausgewertet — kein assoziativer Zugriff auf Vendor/Plugin-Namen. Bei falscher Struktur greift immer der Fallback.
- **Config-Datei-Erstellung**: Fehlt die XML-Konfiguration für eine Vendor/Plugin-Kombination, erstellt `Logconfig::_createConfigXML()` sie automatisch via DOM — erfordert Schreibrechte auf `src/Resources/config/`.
- **ErrorControllerDecorator-Bug**: `$this->originalLoggerService = $errorController;` — Property-Name ist falsch (`originalLoggerService` statt `originalErrorController`), führt aber zu keinem Laufzeitfehler, da die Property nur gesetzt, nie gelesen wird.
- **`http\Message` Import**: In `LoggerServiceDecorator` wird `use http\Message;` importiert, aber nie genutzt — toter Import.

---

## Verwendung in eigenen Plugins

```php
// services.xml
<argument type="service" id="Psr\Log\LoggerInterface"/>

// PHP
$this->logger->error('Fehlermeldung', ['lightsOn', 'myPlugin']);
$this->logger->info('Info', ['lightsOn', 'myPlugin']);

// Mit E-Mail-Benachrichtigung
$this->logger->error('Kritischer Fehler', [
    'lightsOn',
    'myPlugin',
    ['setup' => ['notificationEmail' => true]]
]);
```

Log-Datei landet in: `var/log/myPlugin.lightsOn.log`