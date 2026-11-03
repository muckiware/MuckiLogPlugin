# MuckiLogPlugin – CLAUDE.md

## Zweck

`muckiware/log-plugin` (v2.6.0) ersetzt das Standard-Monolog-Logging von Shopware durch **Apache Log4php** mittels des Symfony **Decorator-Patterns**. Jede Log-Ausgabe, die in Shopware oder Plugins über `Psr\Log\LoggerInterface` erfolgt, wird automatisch abgefangen und durch Log4php geroutet — ohne Codeänderungen in anderen Plugins.

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
| `AdminChangeLogEntity` | `muwa_admin_change_log` | Änderungslog (Commits mit Diff als JSON `changes`) |

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
| `activeChangeLog` | bool | Änderungslog an/aus (Default: aus) |
| `changeLogEntities` | json | Getrackte Hauptentitäten (Default: product, category, order, customer, cms_page) |
| `changeLogRetentionDays` | int | Aufbewahrung in Tagen (Default 90, `0` = nie löschen) |

---

## Scheduled Task

`SendNotificationLogsTask` — läuft alle 300 Sekunden, versendet ausstehende Log-Benachrichtigungen aus `muwa_logging_event`.

`CleanupChangeLogTask` (`muwa.cleanup_change_log_task`) — täglich, löscht Einträge aus `muwa_admin_change_log` älter als `changeLogRetentionDays` (DBAL in Blöcken; `0` oder inaktiv = nichts).

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
- **ErrorControllerDecorator 6.6/6.7**: Header/Footer-Pagelets werden nur gesetzt, wenn `ErrorTemplateStruct` `setHeader()`/`setFooter()` besitzt (6.6 ohne Cache-Rework); in 6.7 kommen sie per ESI. `onCaptchaFailure()` delegiert an den Core-Controller (`.inner`), damit immer die versionsspezifische Implementierung greift.
- **`http\Message` Import**: In `LoggerServiceDecorator` wird `use http\Message;` importiert, aber nie genutzt — toter Import.

---

## Admin-Änderungslog

Audit-Log (Git-Log-Stil): Pro Speichervorgang und Hauptdatensatz ein „Commit“ mit Vorher/Nachher-Diff. Design: `docs/superpowers/specs/2026-11-03-admin-change-log-design.md`.

- **Code:** `src/ChangeLog` (Writer, CommitBuilder, RootResolver, SourceResolver, FieldChangeExtractor, SensitiveFieldMasker, ValueNormalizer, EntityLabelResolver), `src/Subscriber/ChangeLogWriteSubscriber.php` (DAL: `PreWriteValidationEvent` + `EntityWrittenContainerEvent`), `src/Subscriber/SystemConfigChangeSubscriber.php`, `src/Core/AdminChangeLog`, `src/Administration/Controller/ChangeLogSettingsController.php`, Admin-Modul `muwa-change-log`.
- **Quellen:** user / integration / sales_channel / system; IP nur mit Request. Unterdaten (Übersetzungen, Owned-Children inkl. One-to-One, Mapping-Zuordnungen) erscheinen im Diff der obersten getrackten Root; Varianten haben eigene Commits.
- **ACL:** `muwa_change_log.viewer`; Log ist im Admin nur lesbar.
- **Maskierung:** Secrets (auch verschachtelte Keys) als `***` auf beiden Seiten.

### Bekannte Grenzen

- Indexer-gepflegte / WriteProtected-Spalten (z. B. `amountTotal`, `cheapestPrice`) werden nicht geloggt, nur die Quellfelder.
- Direktes SQL wird nicht erfasst (außer `system_config`).
- `system_config` wird aus dem Before-Event geschrieben; ein fehlschlagendes Speichern kann trotzdem geloggt werden.
- Draft-Version-Writes werden übersprungen; nur die gemergte Live-Änderung wird geloggt.
- Bei aktivem Feature-Flag `v6.8.0.0` werden die Detail-Tabs (`sw-tabs`) nicht angezeigt.

### Fallstricke

- **`ChangeSetAware` ist `@internal`** (Shopware-Core). Mechanismus seit 6.4 stabil, aber bei Core-Updates (6.6/6.7) per Tests absichern.
- **`system_config` hat einen eigenen Erfassungsweg:** `SystemConfigService` schreibt per `Connection` am DAL vorbei; deshalb `SystemConfigChangeSubscriber` auf die Before-Events. DAL-Writes auf `system_config` werden im DAL-Weg ignoriert (sonst doppelt).
- **`RootResolver` und `SourceResolver` sind `ResetInterface`** (Reset über `kernel.reset`); sie halten Caches, die zwischen Requests/Messenger-Jobs geleert werden müssen.
- **`CommitBuilder` ist kein Service**, sondern wird pro Event neu instanziiert (hält Zustand pro Speichervorgang).
- Fehler im Änderungslog dürfen das Schreiben nie abbrechen: `Throwable` fangen und mit `['muwa', 'admin-change-log']` loggen.

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