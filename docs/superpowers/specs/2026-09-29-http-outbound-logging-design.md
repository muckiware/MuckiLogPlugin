# Konzept: Outbound-HTTP-Request-Logging

- **Datum:** 2026-09-29
- **Plugin:** `muckiware/log-plugin` (`MuckiLogPlugin`)
- **Status:** Umgesetzt (Scope: `http_client` + Store-/FRW-API). App-System-/Webhook-Guzzle-Clients bewusst nicht umgesetzt, siehe „Bekannte Einschränkung".

## Ziel-Umgebung

- Shopware Version: `>= 6.6.0` (Projekt läuft aktuell auf 6.7; Plugin testet laut CI gegen 6.6 **und** 6.7 – `main-test-sw66.yml`/`main-test-sw67.yml`)
- PHP Version: `>= 8.2`

## Anforderung

Alle ausgehenden HTTP-Requests aus Shopware/Plugins sollen mitgeloggt werden:

- externe URL (inkl. Query-Parameter)
- HTTP-Methode
- Request-Body (falls vorhanden)
- Response-Status und Response-Body

**Finaler Scope:** Symfony `http_client` und Store-/FRW-API. Kein automatisches Masking sensibler Daten (bewusste Entscheidung, siehe Risiken). Feature ist **Opt-in** über einen eigenen Config-Schalter (Default: aus). **Explizit außen vor:** App-System-/Webhook-Guzzle-Clients (technischer Blocker, siehe „Bekannte Einschränkung") sowie Drittanbieter-Plugins mit rohem `curl_*` (z. B. `PixupTranslatorSW6`) – technisch nicht über DI/Decoration erfassbar. Alle geloggten Quellen landen in **einer gemeinsamen** Log-Datei, keine getrennten Dateien pro Client.

## Technischer Ansatz

Wiederverwendung des im Plugin etablierten **Decorator-Patterns** (siehe `LoggerServiceDecorator`) und des bestehenden Logging-Stacks:

```
Psr\Log\LoggerInterface → LoggerServiceDecorator → MuckiLogPlugin\Logging\Logger → Log4php → var/log/<plugin>.<vendor>.log
```

Es wird **keine neue Persistenz und keine neue Admin-UI** benötigt: Der bestehende `LogViewerController`/`muwa-log-viewer` (siehe `docs/superpowers/specs/2026-08-19-log-viewer-design.md`) listet bereits alle `var/log/*.log`-Dateien per `glob()` – eine neue Log-Datei für HTTP-Traffic erscheint dort automatisch.

Pro HTTP-Einstiegspunkt wird ein Decorator/eine Middleware ergänzt, die die eigentliche Anfrage unverändert durchreicht und parallel über `Psr\Log\LoggerInterface` loggt (damit automatisch durch den bestehenden Log4php-Stack inkl. Level-Filterung läuft):

1. **`HttpClientLoggerDecorator`** – `decorates="Symfony\Contracts\HttpClient\HttpClientInterface"` (Service-ID `http_client`). Deckt Store-Updates, App-Downloader, SSO-Token-Service, S3-Adapter und PSR-18-Aufrufe ab (PSR-18 ist nur ein Wrapper um `http_client`). Response-Logging via eigenem `ResponseInterface`-Wrapper (`LoggingResponse`, analog Symfonys eigenem `TraceableResponse`), damit der Body erst beim tatsächlichen `getContent()`/`toArray()`-Zugriff gepuffert und geloggt wird, ohne den Chunk-/Stream-Charakter des Original-Response zu brechen.
2. **`StoreClientLoggingMiddleware`** – implementiert `Shopware\Core\Framework\Store\Services\MiddlewareInterface`, getaggt mit `shopware.store_client.middleware` **und** `shopware.frw_client.middleware` (offizieller Middleware-Tag-Mechanismus, keine Decoration nötig).

App-System-/Webhook-Guzzle-Clients (`shopware.app_system.guzzle`, `shopware.webhook.guzzle`) wurden bewusst **nicht** umgesetzt, siehe „Bekannte Einschränkung".

Gemeinsame Formatierungslogik in einer neuen Klasse `HttpRequestLogFormatter`, damit Request/Response-Format über beide Decorators/Middlewares identisch ist und die Context-Array-Konvention (`[vendor, plugin, ...]`) korrekt bedient wird.

**Log-Datei-Strategie (final):** ein gemeinsames Logfile für den gesamten Outbound-Traffic (`var/log/outbound-http.muwa.log`, Context `['muwa', 'outbound-http']`), Quelle je Eintrag über ein Präfix (`[http_client]`, `[store_client]`) unterscheidbar. Beide Decorators/Middlewares nutzen denselben Context, keine getrennten Dateien.

**Response-/Stream-Handling (wichtig für Korrektheit):**
- Guzzle: Body nach dem Lesen per `$stream->rewind()` zurückspulen, damit der ursprüngliche Aufrufer den Body weiterhin konsumieren kann.
- Symfony HttpClient: Logging lazy beim ersten Content-Zugriff (wie `TraceableResponse`), damit Streaming-Verhalten nicht verändert wird.
- Exceptions (z. B. `ConnectException`, `TransferException`) werden ebenfalls geloggt (Guzzle: `otherwise()`-Callback der Middleware; `http_client`: try/catch im Decorator).

## Komponenten

### Entities
- Keine neuen Entities/Tabellen – reines Datei-Logging wie beim bestehenden Mechanismus.

### Services
- **`HttpRequestLogFormatterInterface` / `HttpRequestLogFormatter`**
  - Zweck: einheitliches Log-Message-Format für Request und Response
  - Dependencies: keine
  - Methoden: `formatRequest(string $source, string $method, string $url, array $headers, ?string $body): string`, `formatResponse(string $source, int $status, array $headers, ?string $body): string`, `formatError(string $source, \Throwable $e): string`
- **`HttpClientLoggerDecorator`** (implements `Symfony\Contracts\HttpClient\HttpClientInterface`, decorates `Symfony\Contracts\HttpClient\HttpClientInterface`)
  - Dependencies: `.inner` HttpClientInterface, `Psr\Log\LoggerInterface`, `HttpRequestLogFormatterInterface`, `MuckiLogPlugin\Services\SettingsInterface`
  - Methoden: `request()`, `stream()`
- **`LoggingResponse`** (implements `Symfony\Contracts\HttpClient\ResponseInterface`)
  - Wrapper um innere Response, loggt beim ersten Content-Zugriff
- **`StoreClientLoggingMiddleware`** (implements `Shopware\Core\Framework\Store\Services\MiddlewareInterface`)
  - Dependencies: `Psr\Log\LoggerInterface`, `HttpRequestLogFormatterInterface`, `SettingsInterface`
  - Tags: `shopware.store_client.middleware`, `shopware.frw_client.middleware`
  - Gemeinsame Guzzle-Logging-Logik (Request/Response/Error lesen + Stream zurückspulen) ausgelagert in `Http\GuzzleLoggingTrait`
- **`Settings` / `SettingsInterface`** (Erweiterung)
  - neue Methode `isHttpLoggingEnabled(): bool`

### Events
- Keine neuen Events – bestehende `LoggerEvents::CREATE_LOG_EVENT_BEFORE/AFTER` laufen automatisch mit, da alles über `Psr\Log\LoggerInterface` geht.

### Subscriber
- Keine neuen.

### Commands
- Keine neuen (MVP).

### Storefront
- Nicht betroffen.

### Administration
- Keine neue UI nötig – bestehender `muwa-log-viewer` zeigt neue Log-Dateien automatisch an (glob-basiert über `var/log/*.log`).

## Migrations
- Keine (kein neues DB-Schema).

## Konfiguration

Neue Karte „Outbound HTTP logging settings" in `config.xml`:

| Key | Typ | Default | Bedeutung |
|---|---|---|---|
| `activeHttpLogging` | bool | `false` | Opt-in-Schalter für das gesamte Feature |

Kein Masking-Schalter, kein Body-Size-Limit – laut Klärung bewusst vollständiges, ungekürztes Logging ohne automatische Redaction.

Vorhandener globaler `level`-Schalter greift weiterhin (Log4php-Level-Filterung); HTTP-Logs werden auf Level `INFO` geschrieben, Fehler/Exceptions auf `ERROR`.

## Bekannte Einschränkung

**Rohes `curl_*` bleibt unsichtbar:** Drittanbieter-Plugins, die direkt `curl_*` statt Guzzle/Symfony-HttpClient nutzen (aktuell bekannt: `PixupTranslatorSW6`, `src/Content/Assistant/Client/AssistantClient.php` und `src/Translator/Bridge/TranslationBridge.php`), gehen am Symfony-Container vorbei – kein Decorator/keine Middleware kann das abfangen (`curl_*` sind native PHP-Funktionen ohne DI-Abstraktion, der Container hat keinen Ansatzpunkt). **Explizit bestätigt und akzeptiert** – Code-Änderungen an Fremd-Plugins oder eine Netzwerk-/Proxy-Lösung sind ausdrücklich **außerhalb** dieses Konzepts.

**App-System-/Webhook-Guzzle-Clients wurden nicht umgesetzt (Entscheidung: Scope auf `http_client` + Store-API reduzieren):** Beim Implementieren festgestellt, dass `Shopware\Core\Framework\App\ActionButton\Executor` den App-System-Client nicht über `GuzzleHttp\ClientInterface`, sondern über die **konkrete Klasse** `\GuzzleHttp\Client` injiziert bekommt. Ein Decorator, der nur `ClientInterface` implementiert, hätte dort zur Laufzeit einen `TypeError` ausgelöst (betrifft u. a. den Checkout-Gateway-Flow). Mögliche spätere Lösungswege, falls der Scope erweitert werden soll:
1. `GuzzleHttp\Client` extend statt nur `ClientInterface` implementieren (funktioniert, aber Guzzle markiert die Klasse als `@final`, fragil bei Guzzle-Updates).
2. CompilerPass, der die Middleware direkt in den bestehenden `HandlerStack` der beiden Guzzle-Clients einhängt (gleiche Technik wie `LoggerDecoratorCompilerPass`, das es in diesem Plugin schon gibt).

## Risiken

- **Sicherheitsrisiko durch fehlendes Masking:** Secrets (API-Keys, Tokens, Passwörter in Header/Body) landen im Klartext in `var/log/*.log` und sind über den bestehenden Log-Viewer für jeden Admin-User mit ACL `muwa_log_viewer:read` einsehbar. Das ist eine bewusste Entscheidung aus der Klärung – sollte im Review nochmal gegengeprüft werden (z. B. Feature nur temporär für Debugging aktivieren, danach wieder deaktivieren).
- **Log-Volumen/Performance:** Ungekürztes Body-Logging bei großen Payloads (große Store-API-Antworten, S3-Adapter, Update-Downloads) kann Log-Dateien schnell aufblähen und I/O-Last erzeugen. Der bestehende Rolling-File-Appender (`maxfilesize`/`maxbackupindex`) begrenzt zumindest die Gesamtgröße pro Datei.
- **Store-Client-Middleware-Volumen:** Läuft bei jedem Store-API-Call (Update-Checks, Plugin-Suche, Lizenzprüfung) – bei kurzen Intervallen potenziell hohes Log-Volumen.
- **Stream-Handling-Fehler:** Falsches Rewind/Buffering bei Guzzle-Streams oder Symfony-HttpClient-Chunks kann den ursprünglichen Aufrufer brechen (Body nicht mehr lesbar) – durch Tests abgesichert.
- **Deckungslücke curl-basierter Calls und App-System-/Webhook-Traffic** (siehe „Bekannte Einschränkung").

## Umsetzung

Implementiert: `HttpRequestLogFormatter` (+Interface), `HttpClientLoggerDecorator` + `LoggingResponse`, `StoreClientLoggingMiddleware` + `Http\GuzzleLoggingTrait`, `config.xml`-Schalter `activeHttpLogging`, `Settings::isHttpLoggingEnabled()`, `services.xml`-Registrierung, 12 neue Tests (gesamte Plugin-Suite 79/79 grün, PHPStan Level 8 sauber).

Zusätzlich im Zuge der Implementierung behoben: `composer.lock`/`vendor`-Symlink zeigten auf eine veraltete Zweitkopie unter `custom/plugins/MuckiLogPlugin` statt auf dieses Repo unter `custom/static-plugins/MuckiLogPlugin` – bereinigt, damit die neue Funktion im laufenden Shop tatsächlich aktiv ist.
