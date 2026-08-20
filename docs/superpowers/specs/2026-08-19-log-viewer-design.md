# Log-Viewer — Design (MuckiLogPlugin)

- **Datum:** 2026-08-19
- **Plugin:** `muckiware/log-plugin` (`MuckiLogPlugin`)
- **Ziel-Versionen:** Shopware 6.6 **und** 6.7 (gemeinsame Codebasis)
- **Status:** Freigegeben, bereit für Implementierungsplan

## 1. Ziel

Eine neue Administrationsansicht unter **Einstellungen**, die Log-Dateien
des Shops lesbar macht:

1. Dropdown mit allen Dateien in `var/log/*.log`.
2. Nach Auswahl: großer, read-only Anzeigebereich mit dem Inhalt der Datei.
3. Suche innerhalb der angezeigten Log-Datei.

Zusätzlich (bestätigt): **Aktualisieren**-Button und **Download** der Datei.

## 2. Nicht-Ziele (YAGNI)

- Kein Leeren/Löschen von Log-Dateien.
- Kein Live-Tailing/Auto-Refresh (nur manuelles Aktualisieren).
- Keine serverseitige Volltextsuche (Suche läuft clientseitig, siehe §6).
- Keine Verwaltung von Log-Verzeichnissen außerhalb von `var/log`.

## 3. Architektur

Das Feature wird vollständig in `MuckiLogPlugin` integriert, im bestehenden
Namespace `MuckiLogPlugin\` und Code-Stil des Plugins (MIT, Tabs in XML).

- **Backend:** neuer Service + neuer Admin-API-Controller.
- **Frontend:** `src/Resources/app/administration` wird **neu** angelegt
  (das Plugin hat bisher kein Admin-Frontend).
- **Zugriff:** neuer Menüpunkt als `settingsItem` unter Einstellungen,
  geschützt durch eine eigene ACL-Berechtigung.

## 4. Backend (PHP)

### 4.1 Service `MuckiLogPlugin\Services\LogViewer` (+ `LogViewerInterface`)

Eine Verantwortung: Log-Dateien im Log-Verzeichnis auflisten und lesen.
Nutzt den vorhandenen `Settings::getLogPath()`
(= `KernelInterface::getProjectDir() . '/var/log'`).

**Methoden:**

- `listFiles(): array`
  - `glob(logPath . '/*.log')`
  - liefert je Datei `['name' => string, 'size' => int, 'mtime' => int]`
  - sortiert nach `mtime` absteigend (neueste zuerst).
- `readTail(string $file, int $lines, ?int $beforeLine = null): array`
  - liest die letzten `$lines` Zeilen **effizient vom Dateiende** —
    chunkweises Rückwärtslesen (`fseek` vom Ende, blockweise), damit große
    Dateien **nicht** komplett in den Speicher geladen werden.
  - `$beforeLine` (optional) unterstützt „mehr laden": liefert den nächsten
    älteren Block oberhalb einer bereits geladenen Position.
  - Rückgabe: `['content' => string, 'firstLine' => int, 'lastLine' => int,
    'hasMore' => bool, 'totalSize' => int]`.
- `getRealPath(string $file): string`
  - zentrale **Pfad-Validierung** (siehe §4.3), von allen Lese-/Download-
    Operationen genutzt; wirft bei ungültigem Namen eine Exception.

### 4.2 Controller `MuckiLogPlugin\Administration\Controller\LogViewerController`

Admin-API-Routen, Präfix `/api/_action/muwa-log-viewer`,
`_routeScope: ['api']`, ACL `muwa_log_viewer:read`.

| Methode | Route | Zweck | Antwort |
|---|---|---|---|
| GET | `/files` | Dateiliste | `JsonResponse` (`listFiles()`) |
| GET | `/content` | Tail-Chunk | `JsonResponse` (`readTail(...)`) |
| GET | `/download` | Datei-Download | `StreamedResponse` (`Content-Disposition: attachment`) |

Query-Parameter `/content`: `file` (Pflicht), `lines` (Default 2000),
`before` (optional, für „mehr laden").

Fehler → `JsonResponse` mit passendem Status (400 ungültiger/fehlender
Parameter, 404 Datei nicht gefunden), Body `{ 'errors': [{ 'detail': ... }] }`.

### 4.3 Sicherheit / Pfad-Validierung

Kritisch, da hier Server-Dateien gelesen und ausgeliefert werden:

- Eingabe wird auf `basename($file)` reduziert (kein `../`, keine Slashes).
- Muss auf `.log` enden.
- Muss in der `glob`-Whitelist aus `listFiles()` **tatsächlich vorhanden**
  sein — d.h. es kann ausschließlich eine reale Datei aus `var/log/*.log`
  adressiert werden.
- Nur lesender Zugriff. Kein Schreiben, kein Löschen.

### 4.4 Registrierung

- `services.xml`: `LogViewer`, Alias `LogViewerInterface`, Controller
  (mit `KernelInterface`/`Settings`-Argumenten, `public="true"`,
  `controller.service_arguments`-Tag).
- `src/Resources/config/routes.xml`: Import des Controller-Verzeichnisses
  (`Administration/Controller/`).

## 5. Frontend (Administration — neu)

Struktur unter `src/Resources/app/administration/`:

```
src/
  main.js                         # Modul- + ACL-Registrierung, API-Service
  module/muwa-log-viewer/
    index.js                      # Module.register(...) inkl. settingsItem
    page/muwa-log-viewer-index/
      index.js
      muwa-log-viewer-index.html.twig
      muwa-log-viewer-index.scss
    snippet/de-DE.json
    snippet/en-GB.json
  service/muwa-log-viewer.api.service.js
```

### 5.1 Modul & Menüpunkt

- `Module.register('muwa-log-viewer', {...})` mit Route
  `muwa.log.viewer.index` und `settingsItem`
  (`group: 'plugins'`, Icon, Label, `privilege: 'muwa_log_viewer.viewer'`).

### 5.2 Seite `muwa-log-viewer-index`

- `sw-page` mit Card.
- **`sw-single-select`** — Optionen aus API `files` (Label: Name + Größe).
- **Anzeigebereich:** scrollbares, read-only `<pre>` mit dem Tail-Inhalt;
  bei aktiver Suche werden Treffer hervorgehoben.
- **Toolbar:**
  - `sw-text-field` Suche + Treffer-Zähler + Weiter/Zurück + Toggle
    Groß-/Kleinschreibung (clientseitig).
  - `sw-button` **Aktualisieren** (Tail neu laden).
  - `sw-button` **Mehr laden** (älteren Block voranstellen; deaktiviert wenn
    `hasMore = false`).
  - `sw-button` **Download**.

### 5.3 API-Service

`muwa-log-viewer.api.service.js` erweitert `ApiService`:
`listFiles()`, `getContent(file, lines, before)`, `download(file)`
(Download als Blob, damit der Admin-API-Auth-Header mitgeht).

### 5.4 ACL

In `main.js` via `Shopware.Service('privileges').addPrivilegeMappingEntry`:
Kategorie `permissions`, Parent `settings`, Key `muwa_log_viewer`,
Rolle `viewer` → Privileg `muwa_log_viewer:read`.

## 6. Datenfluss

1. Seite lädt → `GET /files` → Dropdown befüllen.
2. Datei gewählt → `GET /content?file=…&lines=2000` → Anzeige.
3. **Mehr laden** → `GET /content?file=…&before=firstLine` → älteren Block
   voranstellen.
4. **Aktualisieren** → Tail erneut holen.
5. **Suche** → clientseitig über den **aktuell geladenen** Text
   (Highlight, Sprung zwischen Treffern, Treffer-Zähler).
6. **Download** → Blob über API-Service, Browser-Download auslösen.

**Bewusste Einschränkung:** Da Tail geladen wird und die Suche clientseitig
läuft, deckt die Suche nur den geladenen Ausschnitt ab. Für ältere Bereiche
zuerst „Mehr laden". Explizit so gewählt (einfach, kein Server-Roundtrip
pro Tastendruck).

## 7. Fehlerbehandlung

- Datei nicht gefunden / nicht in Whitelist / nicht lesbar → JSON-Fehler
  (400/404) → `sw-alert` im Frontend.
- Leeres Log-Verzeichnis → leeres Dropdown mit Hinweistext.
- Anfang der Datei erreicht (`hasMore = false`) → „Mehr laden" deaktiviert.
- Download einer sehr großen Datei → gestreamte Antwort (kein Vollspeicher).

## 8. Kompatibilität Shopware 6.6 & 6.7

Das Plugin testet über GitHub-Workflows bereits gegen beide Versionen
(`main-test-sw66.yml`, `main-test-sw67.yml`); das Feature richtet sich daran aus.

**Backend (unkritisch):** ausschließlich core-/Symfony-APIs, die in 6.6 und
6.7 stabil sind — `KernelInterface` (`getLogDir()`/`getProjectDir()`),
`SystemConfigService`, Route-Attribute mit `_routeScope: ['api']` + `_acl`,
`StreamedResponse`. Keine 6.7-only-APIs. PHP-Anforderung bleibt `>= 8.2`.

**Frontend (Risikobereich):** In 6.7 werden viele `sw-*`-Komponenten zu
deprecated-Wrappern um die neuen Meteor-`mt-*`-Komponenten, **funktionieren
aber weiterhin**; in 6.6 sind die klassischen `sw-*` voll verfügbar. Für eine
gemeinsame Codebasis werden daher bewusst die klassischen Komponenten
verwendet, die in 6.6 **und** 6.7 vorhanden und funktionsfähig sind:
`sw-page`, `sw-card`, `sw-single-select`, `sw-button`, `sw-text-field`,
`sw-alert`. Modul-, `settingsItem`- und ACL-Registrierung sind über 6.6/6.7
stabil. `mt-*`-Komponenten, die es in 6.6 noch nicht gibt, sowie in 6.7
tatsächlich entfernte (nicht nur deprecated) APIs werden vermieden.

**Absicherung:** `bin/build-administration.sh` und PHPUnit müssen unter
**beiden** Versionen grün sein (entlang der vorhandenen dual-CI-Workflows).

**Bekannte Einschränkung (ehrlich):** „deprecated aber funktional" ist der
pragmatische Weg für eine gemeinsame Codebasis. Entfernt eine spätere
6.7-Minor eine dieser `sw-*`-Komponenten wirklich, ist eine Nachführung
nötig. Für 6.6 ↔ 6.7 heute ist der Ansatz tragfähig.

## 9. Tests

- **PHPUnit (Kern):** `LogViewer`
  - `listFiles` — findet `*.log`, liefert Metadaten, Sortierung.
  - `readTail` — letzte N Zeilen, `before`/`hasMore`-Verhalten, effizientes
    Rückwärtslesen.
  - Pfad-Validierung — lehnt Traversal (`../`), Nicht-`.log` und nicht
    existierende Dateien ab.
  - nutzt das vorhandene `tests/var`-Fixture-Muster.
- **Controller-Test:** optional (Route-Scope/ACL, 400/404-Pfade).
- **Frontend (Jest):** leichtgewichtig/optional — das Plugin hat aktuell
  keine Admin-Tests.

## 10. Betroffene / neue Dateien (Überblick)

**Neu — Backend:**
- `src/Services/LogViewer.php`, `src/Services/LogViewerInterface.php`
- `src/Administration/Controller/LogViewerController.php`
- `src/Resources/config/routes.xml`

**Neu — Frontend:** `src/Resources/app/administration/…` (siehe §5).

**Geändert:**
- `src/Resources/config/services.xml` (Service + Controller + Route-Import)

**Tests:** `tests/Services/LogViewerTest.php` (+ Fixtures unter `tests/var`).
