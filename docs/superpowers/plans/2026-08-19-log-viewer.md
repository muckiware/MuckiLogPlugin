# Log-Viewer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Eine neue Administrationsansicht unter *Einstellungen*, die `var/log/*.log`-Dateien in einem Dropdown zur Auswahl anbietet, den Inhalt (Tail, nachladbar) read-only anzeigt, clientseitige Suche mit Highlight bietet sowie Aktualisieren und Download erlaubt.

**Architecture:** Backend-Service `LogViewer` liest das Log-Verzeichnis (über den bestehenden `Settings::getLogPath()`), mit effizientem Rückwärtslesen (Tail) und harter Pfad-Validierung. Ein Admin-API-Controller (Attribut-Routen, ACL-geschützt) stellt `files` / `content` / `download` bereit. Das Admin-Frontend (neu im Plugin) registriert ein Modul mit `settingsItem`, einen API-Service und eine Seite mit Dropdown, Anzeige und Suche.

**Tech Stack:** PHP 8.2, Shopware 6.6 **und** 6.7, Symfony Routing-Attribute, PHPUnit (Plugin-eigene Suite), Shopware Administration (Vue 3, klassische `sw-*`-Komponenten), Webpack-Admin-Build.

**Spec:** `docs/superpowers/specs/2026-08-19-log-viewer-design.md`

## Global Constraints

- **Ziel-Versionen:** Muss unter Shopware **6.6 und 6.7** funktionieren. Nur core-/Symfony-APIs verwenden, die in beiden stabil sind. Im Admin ausschließlich klassische `sw-*`-Komponenten (`sw-page`, `sw-card`, `sw-single-select`, `sw-button`, `sw-text-field`, `sw-alert`) — keine `mt-*`-Komponenten.
- **Namespace/Stil:** Alles im Namespace `MuckiLogPlugin\`. PHP-Dateien mit `declare(strict_types=1);`. `services.xml`/`routes.xml` mit Tabs einrücken (bestehender Plugin-Stil).
- **PHP-Anforderung:** bleibt `>= 8.2` (composer.json unverändert lassen).
- **Sicherheit:** Log-Dateien nur lesen. Jeder Dateizugriff läuft über `LogViewer::getRealPath()` (basename, `.log`-Endung, muss real in `var/log` liegen). Kein Schreiben/Löschen.
- **ACL:** API-Routen mit `PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']`. Admin-Menü/Route mit Privileg `muwa_log_viewer.viewer`.
- **Commits:** Jede Commit-Message endet mit der Trailer-Zeile:
  `Co-Authored-By: Claude Opus 4.8 (1M context) <noreply@anthropic.com>`
- **Testlauf (Backend):** aus dem Shop-Root:
  `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter <Name>`
  (Die `LogViewer`-Tests sind reine Unit-Tests; sie mocken `SettingsInterface` und nutzen temporäre Dateien.)
- **Admin-Build:** aus dem Shop-Root: `ddev exec bin/build-administration.sh` und danach `ddev exec bin/console assets:install`.

---

## File Structure

**Neu — Backend:**
- `src/Services/LogViewerInterface.php` — Vertrag: `listFiles()`, `readTail()`, `getRealPath()`.
- `src/Services/LogViewer.php` — Implementierung (Verzeichnis lesen, Tail, Validierung).
- `src/Administration/Controller/LogViewerController.php` — Admin-API (`files`/`content`/`download`).
- `src/Resources/config/routes.xml` — lädt Attribut-Routen aus `src/Administration/Controller`.

**Neu — Frontend (`src/Resources/app/administration/`):**
- `src/main.js` — Modul-Import, ACL-Mapping, Service-Registrierung.
- `src/module/muwa-log-viewer/index.js` — `Module.register` inkl. `settingsItem`.
- `src/module/muwa-log-viewer/page/muwa-log-viewer-index/index.js` — Seiten-Logik.
- `.../muwa-log-viewer-index.html.twig` — Template.
- `.../muwa-log-viewer-index.scss` — Styles.
- `src/module/muwa-log-viewer/snippet/de-DE.json`, `.../en-GB.json` — Snippets.
- `src/service/muwa-log-viewer.api.service.js` — API-Client.

**Geändert:**
- `src/Resources/config/services.xml` — Service + Interface-Alias + Controller registrieren.

**Tests:**
- `tests/Services/LogViewerTest.php` — Unit-Tests für alle drei Service-Methoden.

---

## Task 1: LogViewer — Pfad-Validierung (`getRealPath`)

**Files:**
- Create: `src/Services/LogViewerInterface.php`
- Create: `src/Services/LogViewer.php`
- Test: `tests/Services/LogViewerTest.php`

**Interfaces:**
- Consumes: `MuckiLogPlugin\Services\SettingsInterface::getLogPath(): string` (bereits vorhanden).
- Produces:
  - `LogViewerInterface::getRealPath(string $file): string` — liefert den validierten absoluten Pfad einer Log-Datei; wirft `\RuntimeException` bei ungültigem/nicht existierendem Namen.
  - Konstruktor: `LogViewer::__construct(SettingsInterface $settings)`.

- [ ] **Step 1: Failing-Test schreiben**

`tests/Services/LogViewerTest.php`:

```php
<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

use PHPUnit\Framework\TestCase;

class LogViewerTest extends TestCase
{
    private string $logDir;

    protected function setUp(): void
    {
        $this->logDir = sys_get_temp_dir() . '/muwa-logviewer-' . uniqid('', true);
        mkdir($this->logDir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->logDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->logDir);
    }

    private function createLogViewer(): LogViewer
    {
        $settings = $this->createMock(SettingsInterface::class);
        $settings->method('getLogPath')->willReturn($this->logDir);

        return new LogViewer($settings);
    }

    public function testGetRealPathReturnsPathForExistingLogFile(): void
    {
        file_put_contents($this->logDir . '/dev.log', "hello\n");
        $logViewer = $this->createLogViewer();

        static::assertSame(
            realpath($this->logDir . '/dev.log'),
            $logViewer->getRealPath('dev.log')
        );
    }

    public function testGetRealPathRejectsNonLogExtension(): void
    {
        file_put_contents($this->logDir . '/secret.txt', "secret\n");
        $logViewer = $this->createLogViewer();

        $this->expectException(\RuntimeException::class);
        $logViewer->getRealPath('secret.txt');
    }

    public function testGetRealPathRejectsTraversalAttempt(): void
    {
        $logViewer = $this->createLogViewer();

        $this->expectException(\RuntimeException::class);
        $logViewer->getRealPath('../../../etc/passwd');
    }

    public function testGetRealPathRejectsMissingFile(): void
    {
        $logViewer = $this->createLogViewer();

        $this->expectException(\RuntimeException::class);
        $logViewer->getRealPath('does-not-exist.log');
    }
}
```

- [ ] **Step 2: Test laufen lassen — muss fehlschlagen**

Run: `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter LogViewerTest`
Expected: FAIL (Klassen `LogViewer` / `LogViewerInterface` existieren nicht).

- [ ] **Step 3: Interface anlegen**

`src/Services/LogViewerInterface.php`:

```php
<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

interface LogViewerInterface
{
    /**
     * @return array<int, array{name: string, size: int, mtime: int}>
     */
    public function listFiles(): array;

    /**
     * @return array{content: string, startByte: int, hasMore: bool, size: int, lines: int}
     */
    public function readTail(string $file, int $maxLines, ?int $beforeByte = null): array;

    public function getRealPath(string $file): string;
}
```

- [ ] **Step 4: `getRealPath` implementieren**

`src/Services/LogViewer.php`:

```php
<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Services;

final class LogViewer implements LogViewerInterface
{
    public function __construct(
        private readonly SettingsInterface $settings
    ) {
    }

    public function getRealPath(string $file): string
    {
        $name = basename($file);
        if ($name === '' || !str_ends_with($name, '.log')) {
            throw new \RuntimeException(sprintf('Invalid log file name "%s".', $file));
        }

        $baseDir = $this->settings->getLogPath();
        $candidate = $baseDir . '/' . $name;

        $real = realpath($candidate);
        $baseReal = realpath($baseDir);

        if ($real === false || $baseReal === false
            || !str_starts_with($real, $baseReal . \DIRECTORY_SEPARATOR)
            || !is_file($real)
            || !is_readable($real)
        ) {
            throw new \RuntimeException(sprintf('Log file "%s" not found.', $file));
        }

        return $real;
    }

    /**
     * @return array<int, array{name: string, size: int, mtime: int}>
     */
    public function listFiles(): array
    {
        return [];
    }

    /**
     * @return array{content: string, startByte: int, hasMore: bool, size: int, lines: int}
     */
    public function readTail(string $file, int $maxLines, ?int $beforeByte = null): array
    {
        return ['content' => '', 'startByte' => 0, 'hasMore' => false, 'size' => 0, 'lines' => 0];
    }
}
```

- [ ] **Step 5: Test laufen lassen — muss bestehen**

Run: `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter LogViewerTest`
Expected: PASS (die 4 `getRealPath`-Tests grün; `listFiles`/`readTail` noch Stubs, aber nicht getestet).

- [ ] **Step 6: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Services/LogViewerInterface.php \
        custom/plugins/MuckiLogPlugin/src/Services/LogViewer.php \
        custom/plugins/MuckiLogPlugin/tests/Services/LogViewerTest.php
git commit -m "feat: LogViewer path validation for log file access"
```

---

## Task 2: LogViewer — Dateiliste (`listFiles`)

**Files:**
- Modify: `src/Services/LogViewer.php`
- Test: `tests/Services/LogViewerTest.php`

**Interfaces:**
- Produces: `LogViewer::listFiles(): array<int, array{name:string,size:int,mtime:int}>` — nur `*.log` aus dem Log-Verzeichnis, absteigend nach `mtime` sortiert.

- [ ] **Step 1: Failing-Test ergänzen**

In `LogViewerTest` hinzufügen:

```php
    public function testListFilesReturnsOnlyLogFilesSortedByMtimeDesc(): void
    {
        file_put_contents($this->logDir . '/old.log', "a\n");
        file_put_contents($this->logDir . '/new.log', "bb\n");
        file_put_contents($this->logDir . '/ignore.txt', "nope\n");
        touch($this->logDir . '/old.log', 1000);
        touch($this->logDir . '/new.log', 2000);

        $files = $this->createLogViewer()->listFiles();

        static::assertCount(2, $files);
        static::assertSame('new.log', $files[0]['name']);
        static::assertSame('old.log', $files[1]['name']);
        static::assertSame(2, $files[0]['size']);
        static::assertSame(2000, $files[0]['mtime']);
    }
```

- [ ] **Step 2: Test laufen lassen — muss fehlschlagen**

Run: `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter testListFilesReturnsOnlyLogFilesSortedByMtimeDesc`
Expected: FAIL (`listFiles` liefert leeres Array).

- [ ] **Step 3: `listFiles` implementieren**

In `src/Services/LogViewer.php` die Methode `listFiles` ersetzen durch:

```php
    /**
     * @return array<int, array{name: string, size: int, mtime: int}>
     */
    public function listFiles(): array
    {
        $baseDir = $this->settings->getLogPath();
        $paths = glob($baseDir . '/*.log') ?: [];

        $files = [];
        foreach ($paths as $path) {
            if (!is_file($path)) {
                continue;
            }

            $files[] = [
                'name' => basename($path),
                'size' => (int) filesize($path),
                'mtime' => (int) filemtime($path),
            ];
        }

        usort($files, static fn (array $a, array $b): int => $b['mtime'] <=> $a['mtime']);

        return $files;
    }
```

- [ ] **Step 4: Test laufen lassen — muss bestehen**

Run: `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter LogViewerTest`
Expected: PASS (alle bisherigen Tests grün).

- [ ] **Step 5: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Services/LogViewer.php \
        custom/plugins/MuckiLogPlugin/tests/Services/LogViewerTest.php
git commit -m "feat: list available log files sorted by modification time"
```

---

## Task 3: LogViewer — Tail lesen (`readTail`)

**Files:**
- Modify: `src/Services/LogViewer.php`
- Test: `tests/Services/LogViewerTest.php`

**Interfaces:**
- Produces: `LogViewer::readTail(string $file, int $maxLines, ?int $beforeByte = null): array{content:string,startByte:int,hasMore:bool,size:int,lines:int}`
  - Ohne `beforeByte`: die letzten `$maxLines` Zeilen (Tail).
  - Mit `beforeByte`: die letzten `$maxLines` Zeilen, die **vor** Byte-Offset `$beforeByte` enden (für „mehr laden").
  - `startByte` = Byte-Offset, an dem der zurückgegebene Ausschnitt beginnt (als nächstes `beforeByte` verwenden). `hasMore` = `startByte > 0`.

- [ ] **Step 1: Failing-Test ergänzen**

In `LogViewerTest` hinzufügen:

```php
    private function writeNumberedLines(string $name, int $count): string
    {
        $content = '';
        for ($i = 1; $i <= $count; $i++) {
            $content .= 'line' . $i . "\n";
        }
        file_put_contents($this->logDir . '/' . $name, $content);

        return $this->logDir . '/' . $name;
    }

    public function testReadTailReturnsLastLines(): void
    {
        $path = $this->writeNumberedLines('dev.log', 10);
        $size = (int) filesize($path);

        $chunk = $this->createLogViewer()->readTail('dev.log', 3);

        static::assertSame("line8\nline9\nline10\n", $chunk['content']);
        static::assertSame(3, $chunk['lines']);
        static::assertTrue($chunk['hasMore']);
        static::assertSame($size, $chunk['size']);
        static::assertSame($size - strlen($chunk['content']), $chunk['startByte']);
    }

    public function testReadTailWholeFileHasNoMore(): void
    {
        $this->writeNumberedLines('dev.log', 4);

        $chunk = $this->createLogViewer()->readTail('dev.log', 100);

        static::assertSame("line1\nline2\nline3\nline4\n", $chunk['content']);
        static::assertFalse($chunk['hasMore']);
        static::assertSame(0, $chunk['startByte']);
    }

    public function testReadTailLoadMoreReturnsOlderLines(): void
    {
        $this->writeNumberedLines('dev.log', 10);
        $logViewer = $this->createLogViewer();

        $first = $logViewer->readTail('dev.log', 3);
        $second = $logViewer->readTail('dev.log', 3, $first['startByte']);

        static::assertSame("line5\nline6\nline7\n", $second['content']);
        static::assertTrue($second['hasMore']);
    }
```

- [ ] **Step 2: Test laufen lassen — muss fehlschlagen**

Run: `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter testReadTail`
Expected: FAIL (`readTail` liefert leeren Ausschnitt).

- [ ] **Step 3: `readTail` implementieren**

In `src/Services/LogViewer.php` die Methode `readTail` ersetzen durch:

```php
    /**
     * @return array{content: string, startByte: int, hasMore: bool, size: int, lines: int}
     */
    public function readTail(string $file, int $maxLines, ?int $beforeByte = null): array
    {
        $path = $this->getRealPath($file);
        $size = (int) filesize($path);
        $end = $beforeByte ?? $size;
        $end = max(0, min($end, $size));

        if ($end === 0 || $maxLines < 1) {
            return ['content' => '', 'startByte' => 0, 'hasMore' => false, 'size' => $size, 'lines' => 0];
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException(sprintf('Cannot open log file "%s".', $file));
        }

        $chunkSize = 8192;
        $pos = $end;
        $buffer = '';
        $newlineCount = 0;

        // Rückwärts lesen, bis genug Zeilen-Grenzen (maxLines + 1) oder Dateianfang.
        while ($pos > 0 && $newlineCount <= $maxLines) {
            $read = (int) min($chunkSize, $pos);
            $pos -= $read;
            fseek($handle, $pos);
            $buffer = (string) fread($handle, $read) . $buffer;
            $newlineCount = substr_count($buffer, "\n");
        }
        fclose($handle);

        $hadTrailingNewline = str_ends_with($buffer, "\n");
        $trimmed = $hadTrailingNewline ? substr($buffer, 0, -1) : $buffer;
        $allLines = $trimmed === '' ? [] : explode("\n", $trimmed);

        $kept = array_slice($allLines, -$maxLines);
        $content = implode("\n", $kept);
        if ($content !== '' && $hadTrailingNewline) {
            $content .= "\n";
        }

        $startByte = $end - strlen($content);

        return [
            'content' => $content,
            'startByte' => $startByte,
            'hasMore' => $startByte > 0,
            'size' => $size,
            'lines' => count($kept),
        ];
    }
```

- [ ] **Step 4: Test laufen lassen — muss bestehen**

Run: `ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter LogViewerTest`
Expected: PASS (alle Service-Tests grün).

- [ ] **Step 5: PHPStan prüfen**

Run: `ddev exec bash -c "cd custom/plugins/MuckiLogPlugin && composer phpstan"`
Expected: keine Fehler in `src/Services/LogViewer.php`.

- [ ] **Step 6: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Services/LogViewer.php \
        custom/plugins/MuckiLogPlugin/tests/Services/LogViewerTest.php
git commit -m "feat: efficient tail reading with byte-based pagination"
```

---

## Task 4: Admin-API-Controller + Routing + DI

**Files:**
- Create: `src/Administration/Controller/LogViewerController.php`
- Create: `src/Resources/config/routes.xml`
- Modify: `src/Resources/config/services.xml`

**Interfaces:**
- Consumes: `LogViewerInterface` (Task 1–3).
- Produces (HTTP, Präfix `/api/_action/muwa-log-viewer`, ACL `muwa_log_viewer:read`):
  - `GET /files` → `{"files": [{name,size,mtime}, ...]}`
  - `GET /content?file=<name>&lines=<int>&before=<int>` → `{content,startByte,hasMore,size,lines}`
  - `GET /download?file=<name>` → Datei-Download (`attachment`)

- [ ] **Step 1: Controller anlegen**

`src/Administration/Controller/LogViewerController.php`:

```php
<?php

declare(strict_types=1);

namespace MuckiLogPlugin\Administration\Controller;

use MuckiLogPlugin\Services\LogViewerInterface;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
final class LogViewerController
{
    public function __construct(
        private readonly LogViewerInterface $logViewer
    ) {
    }

    #[Route(
        path: '/api/_action/muwa-log-viewer/files',
        name: 'api.action.muwa_log_viewer.files',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']],
        methods: ['GET']
    )]
    public function files(): JsonResponse
    {
        return new JsonResponse(['files' => $this->logViewer->listFiles()]);
    }

    #[Route(
        path: '/api/_action/muwa-log-viewer/content',
        name: 'api.action.muwa_log_viewer.content',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']],
        methods: ['GET']
    )]
    public function content(Request $request): JsonResponse
    {
        $file = (string) $request->query->get('file', '');
        if ($file === '') {
            return new JsonResponse(
                ['errors' => [['detail' => 'Missing "file" parameter.']]],
                Response::HTTP_BAD_REQUEST
            );
        }

        $lines = max(1, min((int) $request->query->get('lines', 2000), 20000));
        $beforeByte = $request->query->has('before') ? (int) $request->query->get('before') : null;

        try {
            $chunk = $this->logViewer->readTail($file, $lines, $beforeByte);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(
                ['errors' => [['detail' => $exception->getMessage()]]],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse($chunk);
    }

    #[Route(
        path: '/api/_action/muwa-log-viewer/download',
        name: 'api.action.muwa_log_viewer.download',
        defaults: [PlatformRequest::ATTRIBUTE_ACL => ['muwa_log_viewer:read']],
        methods: ['GET']
    )]
    public function download(Request $request): Response
    {
        $file = (string) $request->query->get('file', '');

        try {
            $path = $this->logViewer->getRealPath($file);
        } catch (\RuntimeException $exception) {
            return new JsonResponse(
                ['errors' => [['detail' => $exception->getMessage()]]],
                Response::HTTP_NOT_FOUND
            );
        }

        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, basename($path));

        return $response;
    }
}
```

- [ ] **Step 2: routes.xml anlegen**

`src/Resources/config/routes.xml`:

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<routes xmlns="http://symfony.com/schema/routing"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/routing https://symfony.com/schema/routing/routing-1.0.xsd">
	<import resource="../../Administration/Controller" type="attribute"/>
</routes>
```

- [ ] **Step 3: Service registrieren**

In `src/Resources/config/services.xml` innerhalb `<services>` (z. B. nach dem `Helper`-Service) einfügen:

```xml
        <service id="MuckiLogPlugin\Services\LogViewer">
        	<argument type="service" id="MuckiLogPlugin\Services\SettingsInterface"/>
        </service>
        <service id="MuckiLogPlugin\Services\LogViewerInterface" alias="MuckiLogPlugin\Services\LogViewer"/>

        <service id="MuckiLogPlugin\Administration\Controller\LogViewerController" public="true">
        	<argument type="service" id="MuckiLogPlugin\Services\LogViewerInterface"/>
        	<tag name="controller.service_arguments"/>
        </service>
```

- [ ] **Step 4: Cache leeren & Routen prüfen**

Run:
```bash
ddev exec bin/console cache:clear
ddev exec bin/console debug:router | grep muwa-log-viewer
```
Expected: drei Zeilen mit `api.action.muwa_log_viewer.files`, `.content`, `.download` und Pfaden unter `/api/_action/muwa-log-viewer/...`.

- [ ] **Step 5: Endpunkt smoke-testen (authentifiziert)**

Run (Admin-Token holen und `files` aufrufen):
```bash
ddev exec bash -c '
TOKEN=$(curl -s -X POST http://localhost/api/oauth/token \
  -H "Content-Type: application/json" \
  -d "{\"grant_type\":\"password\",\"client_id\":\"administration\",\"username\":\"admin\",\"password\":\"shopware\"}" \
  | sed -E "s/.*\"access_token\":\"([^\"]+)\".*/\1/")
curl -s http://localhost/api/_action/muwa-log-viewer/files -H "Authorization: Bearer $TOKEN"'
```
Expected: JSON `{"files":[...]}` mit den Dateien aus `var/log`. (Zugangsdaten ggf. an die lokale Umgebung anpassen.)

- [ ] **Step 6: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Administration/Controller/LogViewerController.php \
        custom/plugins/MuckiLogPlugin/src/Resources/config/routes.xml \
        custom/plugins/MuckiLogPlugin/src/Resources/config/services.xml
git commit -m "feat: admin API endpoints for log files, content and download"
```

---

## Task 5: Admin-Modul, ACL & Menüpunkt (Gerüst)

**Files:**
- Create: `src/Resources/app/administration/src/main.js`
- Create: `src/Resources/app/administration/src/module/muwa-log-viewer/index.js`
- Create: `src/Resources/app/administration/src/module/muwa-log-viewer/page/muwa-log-viewer-index/index.js`
- Create: `.../muwa-log-viewer-index.html.twig`
- Create: `.../muwa-log-viewer-index.scss`
- Create: `src/Resources/app/administration/src/module/muwa-log-viewer/snippet/de-DE.json`
- Create: `.../snippet/en-GB.json`

**Interfaces:**
- Produces: Menüpunkt unter *Einstellungen* → Route `muwa.log.viewer.index`, Komponente `muwa-log-viewer-index`, Privileg `muwa_log_viewer.viewer` (ACL-Mapping-Key `muwa_log_viewer`, Rolle `viewer` → API-Privileg `muwa_log_viewer:read`).
- Alle in späteren Tasks verwendeten Snippet-Keys werden hier vollständig angelegt.

- [ ] **Step 1: main.js anlegen**

`src/Resources/app/administration/src/main.js`:

```js
import './module/muwa-log-viewer';
import './service/muwa-log-viewer.api.service';

Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'muwa_log_viewer',
    roles: {
        viewer: {
            privileges: ['muwa_log_viewer:read'],
            dependencies: [],
        },
    },
});
```

- [ ] **Step 2: Modul registrieren**

`src/module/muwa-log-viewer/index.js`:

```js
import './page/muwa-log-viewer-index';
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

const { Module } = Shopware;

Module.register('muwa-log-viewer', {
    type: 'plugin',
    name: 'muwa-log-viewer',
    title: 'muwa-log-viewer.general.mainMenuItemGeneral',
    description: 'muwa-log-viewer.general.description',
    color: '#9AA8B5',
    icon: 'regular-file-text',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB,
    },

    routes: {
        index: {
            component: 'muwa-log-viewer-index',
            path: 'index',
            meta: {
                privilege: 'muwa_log_viewer.viewer',
            },
        },
    },

    settingsItem: [{
        group: 'plugins',
        to: 'muwa.log.viewer.index',
        icon: 'regular-file-text',
        name: 'muwa-log-viewer',
        label: 'muwa-log-viewer.general.mainMenuItemGeneral',
        privilege: 'muwa_log_viewer.viewer',
    }],
});
```

- [ ] **Step 3: Seiten-Gerüst anlegen**

`.../page/muwa-log-viewer-index/index.js`:

```js
import template from './muwa-log-viewer-index.html.twig';
import './muwa-log-viewer-index.scss';

const { Component } = Shopware;

Component.register('muwa-log-viewer-index', {
    template,

    data() {
        return {
            files: [],
            selectedFile: null,
            content: '',
            isLoading: false,
            errorMessage: '',
        };
    },
});
```

`.../muwa-log-viewer-index.html.twig`:

```twig
{% block muwa_log_viewer_index %}
<sw-page class="muwa-log-viewer-index">
    {% block muwa_log_viewer_index_header %}
        <template #smart-bar-header>
            <h2>{{ $tc('muwa-log-viewer.general.mainMenuItemGeneral') }}</h2>
        </template>
    {% endblock %}

    {% block muwa_log_viewer_index_content %}
        <template #content>
            <sw-card-view>
                <sw-alert
                    v-if="errorMessage"
                    variant="error"
                    class="muwa-log-viewer-index__alert"
                >
                    {{ errorMessage }}
                </sw-alert>

                <sw-card :title="$tc('muwa-log-viewer.general.cardTitle')">
                    <p>{{ $tc('muwa-log-viewer.general.placeholder') }}</p>
                </sw-card>
            </sw-card-view>
        </template>
    {% endblock %}
</sw-page>
{% endblock %}
```

`.../muwa-log-viewer-index.scss`:

```scss
.muwa-log-viewer-index {
    &__alert {
        margin-bottom: 16px;
    }

    &__toolbar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 12px;
        flex-wrap: wrap;
    }

    &__search {
        flex: 1 1 240px;
    }

    &__matches {
        white-space: nowrap;
        color: $color-darkgray-200;
    }

    &__viewer {
        max-height: 60vh;
        overflow: auto;
        background: $color-gray-100;
        border: 1px solid $color-gray-300;
        border-radius: 4px;
        padding: 12px;
        font-family: $font-family-monospace;
        font-size: 12px;
        line-height: 1.5;
        white-space: pre-wrap;
        word-break: break-all;

        mark {
            background: $color-yellow-300;
            padding: 0;

            &.is-active {
                background: $color-orange-300;
            }
        }
    }
}
```

- [ ] **Step 4: Snippets anlegen (vollständig für alle Tasks)**

`.../snippet/de-DE.json`:

```json
{
    "muwa-log-viewer": {
        "general": {
            "mainMenuItemGeneral": "Log-Viewer",
            "description": "Log-Dateien ansehen und durchsuchen",
            "cardTitle": "Log-Dateien",
            "placeholder": "Bitte eine Log-Datei auswählen."
        },
        "index": {
            "fileLabel": "Log-Datei",
            "filePlaceholder": "Datei auswählen …",
            "searchPlaceholder": "In dieser Datei suchen …",
            "matches": "{current} von {total}",
            "noMatches": "Keine Treffer",
            "refresh": "Aktualisieren",
            "loadMore": "Mehr laden",
            "download": "Herunterladen",
            "empty": "Keine Log-Dateien gefunden.",
            "truncatedHint": "Es werden die neuesten Zeilen angezeigt. Für ältere Einträge „Mehr laden“."
        }
    }
}
```

`.../snippet/en-GB.json`:

```json
{
    "muwa-log-viewer": {
        "general": {
            "mainMenuItemGeneral": "Log viewer",
            "description": "View and search log files",
            "cardTitle": "Log files",
            "placeholder": "Please select a log file."
        },
        "index": {
            "fileLabel": "Log file",
            "filePlaceholder": "Select file …",
            "searchPlaceholder": "Search in this file …",
            "matches": "{current} of {total}",
            "noMatches": "No matches",
            "refresh": "Refresh",
            "loadMore": "Load more",
            "download": "Download",
            "empty": "No log files found.",
            "truncatedHint": "Showing the most recent lines. Use “Load more” for older entries."
        }
    }
}
```

- [ ] **Step 5: Admin-Build & manuelle Prüfung**

Run:
```bash
ddev exec bin/build-administration.sh
ddev exec bin/console assets:install
```
Expected: Build ohne Fehler. Danach im Admin (nach Reload/Login) unter **Einstellungen → Plugins** erscheint „Log-Viewer“; die Seite öffnet mit Karte und Platzhaltertext.

- [ ] **Step 6: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Resources/app/administration
git commit -m "feat: admin log viewer module, settings item and ACL mapping"
```

---

## Task 6: Admin-API-Service

**Files:**
- Create: `src/Resources/app/administration/src/service/muwa-log-viewer.api.service.js`

**Interfaces:**
- Consumes: API-Routen aus Task 4.
- Produces: Service `muwaLogViewerService` mit:
  - `listFiles(): Promise<{files: Array<{name,size,mtime}>}>`
  - `getContent(file: string, lines = 2000, before: number|null = null): Promise<{content,startByte,hasMore,size,lines}>`
  - `download(file: string): Promise<Blob>`

- [ ] **Step 1: Service anlegen**

`src/service/muwa-log-viewer.api.service.js`:

```js
const { Application, Classes: { ApiService } } = Shopware;

class MuwaLogViewerApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'muwa-log-viewer') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'muwaLogViewerService';
    }

    listFiles() {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/files`, { headers })
            .then((response) => ApiService.handleResponse(response));
    }

    getContent(file, lines = 2000, before = null) {
        const headers = this.getBasicHeaders();
        const params = { file, lines };
        if (before !== null) {
            params.before = before;
        }

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/content`, { headers, params })
            .then((response) => ApiService.handleResponse(response));
    }

    download(file) {
        const headers = this.getBasicHeaders();

        return this.httpClient
            .get(`/_action/${this.getApiBasePath()}/download`, {
                headers,
                params: { file },
                responseType: 'blob',
            })
            .then((response) => response.data);
    }
}

Application.addServiceProvider('muwaLogViewerService', (container) => {
    const initContainer = Application.getContainer('init');
    return new MuwaLogViewerApiService(initContainer.httpClient, container.loginService);
});
```

- [ ] **Step 2: Admin-Build**

Run: `ddev exec bin/build-administration.sh`
Expected: Build ohne Fehler (Service wird über `main.js`-Import gebündelt).

- [ ] **Step 3: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Resources/app/administration/src/service/muwa-log-viewer.api.service.js
git commit -m "feat: admin api service client for log viewer endpoints"
```

---

## Task 7: Seite — Dropdown, Anzeige, Aktualisieren, Mehr laden, Download

**Files:**
- Modify: `.../page/muwa-log-viewer-index/index.js`
- Modify: `.../muwa-log-viewer-index.html.twig`

**Interfaces:**
- Consumes: `muwaLogViewerService` (Task 6).
- Produces: geladener Zustand `content` + `startByte`/`hasMore` für die Suche in Task 8; Methoden `loadFiles`, `onFileChange`, `refresh`, `loadMore`, `onDownload`.

- [ ] **Step 1: Seiten-Logik ersetzen**

`.../page/muwa-log-viewer-index/index.js` vollständig ersetzen durch:

```js
import template from './muwa-log-viewer-index.html.twig';
import './muwa-log-viewer-index.scss';

const { Component } = Shopware;

Component.register('muwa-log-viewer-index', {
    template,

    inject: ['muwaLogViewerService'],

    data() {
        return {
            files: [],
            selectedFile: null,
            content: '',
            startByte: 0,
            hasMore: false,
            isLoading: false,
            errorMessage: '',
            defaultLines: 2000,
        };
    },

    computed: {
        fileOptions() {
            return this.files.map((file) => ({
                value: file.name,
                label: `${file.name} (${this.formatSize(file.size)})`,
            }));
        },
    },

    created() {
        this.loadFiles();
    },

    methods: {
        formatSize(bytes) {
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }
            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },

        async loadFiles() {
            this.isLoading = true;
            this.errorMessage = '';
            try {
                const response = await this.muwaLogViewerService.listFiles();
                this.files = response.files || [];
            } catch (error) {
                this.errorMessage = error?.response?.data?.errors?.[0]?.detail || error.message;
            } finally {
                this.isLoading = false;
            }
        },

        async onFileChange(fileName) {
            this.selectedFile = fileName;
            this.content = '';
            this.startByte = 0;
            this.hasMore = false;
            if (!fileName) {
                return;
            }
            await this.loadContent(false);
        },

        async loadContent(loadMore) {
            if (!this.selectedFile) {
                return;
            }
            this.isLoading = true;
            this.errorMessage = '';
            try {
                const before = loadMore ? this.startByte : null;
                const chunk = await this.muwaLogViewerService.getContent(
                    this.selectedFile,
                    this.defaultLines,
                    before,
                );
                if (loadMore) {
                    this.content = chunk.content + this.content;
                } else {
                    this.content = chunk.content;
                }
                this.startByte = chunk.startByte;
                this.hasMore = chunk.hasMore;
            } catch (error) {
                this.errorMessage = error?.response?.data?.errors?.[0]?.detail || error.message;
            } finally {
                this.isLoading = false;
            }
        },

        refresh() {
            this.loadContent(false);
        },

        loadMore() {
            this.loadContent(true);
        },

        async onDownload() {
            if (!this.selectedFile) {
                return;
            }
            try {
                const blob = await this.muwaLogViewerService.download(this.selectedFile);
                const url = window.URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = this.selectedFile;
                link.click();
                window.URL.revokeObjectURL(url);
            } catch (error) {
                this.errorMessage = error?.response?.data?.errors?.[0]?.detail || error.message;
            }
        },
    },
});
```

- [ ] **Step 2: Template ersetzen**

`.../muwa-log-viewer-index.html.twig` vollständig ersetzen durch:

```twig
{% block muwa_log_viewer_index %}
<sw-page class="muwa-log-viewer-index">
    {% block muwa_log_viewer_index_header %}
        <template #smart-bar-header>
            <h2>{{ $tc('muwa-log-viewer.general.mainMenuItemGeneral') }}</h2>
        </template>
    {% endblock %}

    {% block muwa_log_viewer_index_content %}
        <template #content>
            <sw-card-view>
                <sw-alert
                    v-if="errorMessage"
                    variant="error"
                    class="muwa-log-viewer-index__alert"
                >
                    {{ errorMessage }}
                </sw-alert>

                <sw-card
                    :title="$tc('muwa-log-viewer.general.cardTitle')"
                    :isLoading="isLoading"
                >
                    <sw-single-select
                        :label="$tc('muwa-log-viewer.index.fileLabel')"
                        :placeholder="$tc('muwa-log-viewer.index.filePlaceholder')"
                        :options="fileOptions"
                        :value="selectedFile"
                        @update:value="onFileChange"
                    />

                    <p v-if="!files.length && !isLoading">
                        {{ $tc('muwa-log-viewer.index.empty') }}
                    </p>

                    <template v-if="selectedFile">
                        <div class="muwa-log-viewer-index__toolbar">
                            <sw-button size="small" @click="refresh">
                                {{ $tc('muwa-log-viewer.index.refresh') }}
                            </sw-button>
                            <sw-button
                                size="small"
                                :disabled="!hasMore"
                                @click="loadMore"
                            >
                                {{ $tc('muwa-log-viewer.index.loadMore') }}
                            </sw-button>
                            <sw-button size="small" @click="onDownload">
                                {{ $tc('muwa-log-viewer.index.download') }}
                            </sw-button>
                        </div>

                        <sw-alert
                            v-if="hasMore"
                            variant="info"
                            :closable="false"
                        >
                            {{ $tc('muwa-log-viewer.index.truncatedHint') }}
                        </sw-alert>

                        <pre class="muwa-log-viewer-index__viewer">{{ content }}</pre>
                    </template>
                </sw-card>
            </sw-card-view>
        </template>
    {% endblock %}
{% endblock %}
```

Hinweis: der abschließende `</sw-page>` steht im Block; falls dein Editor Block-Balance prüft, ersetze die letzten beiden Zeilen durch `    {% endblock %}\n</sw-page>\n{% endblock %}`.

- [ ] **Step 3: Admin-Build & manuelle Prüfung**

Run: `ddev exec bin/build-administration.sh`
Expected: Build ohne Fehler. Im Admin: Datei im Dropdown wählbar → Inhalt (letzte Zeilen) erscheint; **Aktualisieren** lädt neu; **Mehr laden** ist aktiv solange `hasMore`, ergänzt ältere Zeilen oben; **Herunterladen** lädt die Datei.

- [ ] **Step 4: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Resources/app/administration/src/module/muwa-log-viewer/page
git commit -m "feat: log file selection, content view, refresh, load-more and download"
```

---

## Task 8: Clientseitige Suche mit Highlight & Navigation

**Files:**
- Modify: `.../page/muwa-log-viewer-index/index.js`
- Modify: `.../muwa-log-viewer-index.html.twig`

**Interfaces:**
- Consumes: `content` (Task 7).
- Produces: Suche über den geladenen Text — Highlight aller Treffer, Zähler, Vor/Zurück, aktiver Treffer hervorgehoben und in den sichtbaren Bereich gescrollt.

- [ ] **Step 1: Such-State + Logik ergänzen**

In `.../page/muwa-log-viewer-index/index.js`:

`data()` um Suchfelder erweitern (innerhalb des `return { ... }`):

```js
            searchTerm: '',
            caseSensitive: false,
            matchCount: 0,
            currentMatch: 0,
```

`computed` um `highlightedContent` erweitern:

```js
        highlightedContent() {
            if (!this.searchTerm) {
                this.matchCount = 0;
                this.currentMatch = 0;
                return this.escapeHtml(this.content);
            }

            const flags = this.caseSensitive ? 'g' : 'gi';
            const pattern = new RegExp(this.escapeRegExp(this.searchTerm), flags);

            let index = 0;
            const html = this.escapeHtml(this.content).replace(
                new RegExp(this.escapeRegExp(this.escapeHtml(this.searchTerm)), flags),
                (match) => {
                    index += 1;
                    const active = index === this.currentMatch ? ' is-active' : '';
                    return `<mark id="muwa-match-${index}" class="muwa-log-match${active}">${match}</mark>`;
                },
            );

            // matchCount aus dem Rohtext bestimmen (unabhängig vom HTML-Escaping)
            this.matchCount = (this.content.match(pattern) || []).length;
            if (this.matchCount > 0 && this.currentMatch === 0) {
                this.currentMatch = 1;
            }
            if (this.currentMatch > this.matchCount) {
                this.currentMatch = this.matchCount;
            }

            return html;
        },
```

`methods` um Hilfsfunktionen und Navigation erweitern:

```js
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        escapeRegExp(text) {
            return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        onSearchInput(value) {
            this.searchTerm = value;
            this.currentMatch = value ? 1 : 0;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        toggleCaseSensitive() {
            this.caseSensitive = !this.caseSensitive;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        nextMatch() {
            if (this.matchCount === 0) {
                return;
            }
            this.currentMatch = this.currentMatch >= this.matchCount ? 1 : this.currentMatch + 1;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        previousMatch() {
            if (this.matchCount === 0) {
                return;
            }
            this.currentMatch = this.currentMatch <= 1 ? this.matchCount : this.currentMatch - 1;
            this.$nextTick(() => this.scrollToCurrentMatch());
        },

        scrollToCurrentMatch() {
            const element = this.$el.querySelector(`#muwa-match-${this.currentMatch}`);
            if (element) {
                element.scrollIntoView({ block: 'center', behavior: 'smooth' });
            }
        },
```

- [ ] **Step 2: Template um Suchleiste erweitern & Anzeige umstellen**

Im Toolbar-Block (vor den Buttons) einfügen:

```twig
                            <sw-text-field
                                class="muwa-log-viewer-index__search"
                                :placeholder="$tc('muwa-log-viewer.index.searchPlaceholder')"
                                :value="searchTerm"
                                @update:value="onSearchInput"
                            />
                            <span class="muwa-log-viewer-index__matches">
                                <template v-if="searchTerm">
                                    {{ matchCount ? $tc('muwa-log-viewer.index.matches', 0, { current: currentMatch, total: matchCount }) : $tc('muwa-log-viewer.index.noMatches') }}
                                </template>
                            </span>
                            <sw-button size="small" :disabled="!matchCount" @click="previousMatch">‹</sw-button>
                            <sw-button size="small" :disabled="!matchCount" @click="nextMatch">›</sw-button>
                            <sw-button size="small" @click="toggleCaseSensitive">
                                Aa{{ caseSensitive ? ' ✓' : '' }}
                            </sw-button>
```

Die Anzeige-Zeile ersetzen (`<pre>{{ content }}</pre>` → Highlight-HTML):

```twig
                        <pre class="muwa-log-viewer-index__viewer" v-html="highlightedContent"></pre>
```

- [ ] **Step 3: Admin-Build & manuelle Prüfung**

Run: `ddev exec bin/build-administration.sh`
Expected: Build ohne Fehler. Suche im geladenen Text hebt alle Treffer hervor; Zähler zeigt „x von y“; ‹/› springt zwischen Treffern und scrollt den aktiven Treffer in den sichtbaren Bereich; „Aa“ schaltet Groß-/Kleinschreibung um. Bei 0 Treffern erscheint „Keine Treffer“.

- [ ] **Step 4: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/src/Resources/app/administration/src/module/muwa-log-viewer/page
git commit -m "feat: client-side search with highlighting and match navigation"
```

---

## Task 9: Abschluss-Verifikation (6.6 & 6.7) und Doku

**Files:**
- Modify: `readme.md` (kurzer Abschnitt zum Log-Viewer)

**Interfaces:** keine neuen.

- [ ] **Step 1: Vollständige Backend-Suite & PHPStan**

Run:
```bash
ddev exec vendor/bin/phpunit -c custom/plugins/MuckiLogPlugin/phpunit.xml --filter LogViewerTest
ddev exec bash -c "cd custom/plugins/MuckiLogPlugin && composer phpstan"
```
Expected: alle Tests grün, PHPStan ohne Fehler.

- [ ] **Step 2: Manueller End-to-End-Check (lokal = 6.7)**

Prüfen: Menüpunkt unter Einstellungen sichtbar; Dropdown listet `var/log/*.log`; Auswahl zeigt Tail; Mehr laden, Aktualisieren, Download, Suche mit Highlight/Navigation funktionieren; ohne ACL-Privileg `muwa_log_viewer.viewer` ist der Menüpunkt nicht sichtbar und die API antwortet 403.

- [ ] **Step 3: 6.6-Parität absichern (CI)**

Die vorhandenen Workflows `.github/workflows/main-test-sw66.yml` und `main-test-sw67.yml` müssen grün sein. Falls lokal keine 6.6-Instanz vorliegt: Änderungen pushen und die CI beider Workflows abwarten. Nur klassische `sw-*`-Komponenten und stabile core-APIs sind verwendet — es sind keine versionsspezifischen Weichen nötig.

- [ ] **Step 4: readme.md ergänzen**

Kurzer Abschnitt unter einer neuen Überschrift `## Log-Viewer (Administration)`:

```markdown
## Log-Viewer (Administration)

Unter **Einstellungen → Plugins → Log-Viewer** lassen sich die Dateien aus
`var/log/*.log` auswählen und ansehen. Es werden die neuesten Zeilen geladen
(mit „Mehr laden“ für ältere Einträge), der Inhalt ist durchsuchbar
(Highlight, Vor/Zurück, Groß-/Kleinschreibung) und die Datei kann
heruntergeladen werden. Zugriff nur mit dem ACL-Privileg
`muwa_log_viewer.viewer` (API-Privileg `muwa_log_viewer:read`).
```

- [ ] **Step 5: Commit**

```bash
git add custom/plugins/MuckiLogPlugin/readme.md
git commit -m "docs: document the admin log viewer feature"
```

---

## Self-Review Notes

- **Spec-Abdeckung:** Dropdown (`files` + Task 7), Anzeige/Tail (`readTail` Task 3 + Task 7), Suche (Task 8), Aktualisieren/Download (Task 7), ACL (Task 4/5), 6.6+6.7 (Global Constraints + Task 9), Tests (Task 1–3, 9), Pfad-Sicherheit (Task 1). Keine offenen Spec-Punkte.
- **Typ-Konsistenz:** `readTail`-Rückgabe (`content/startByte/hasMore/size/lines`) identisch in Interface, Implementierung, Controller, API-Service und Seite. Privileg-Strings durchgängig: Menü/Route `muwa_log_viewer.viewer`, API `muwa_log_viewer:read`, Mapping-Key `muwa_log_viewer`.
- **Bekannte Einschränkung:** Suche arbeitet nur auf dem geladenen Ausschnitt (bewusst, siehe Spec §6).
