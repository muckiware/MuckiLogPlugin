# Admin-Änderungslog (Audit-Log) – Design

**Datum:** 2026-11-03
**Plugin:** `muckiware/log-plugin` (MuckiLogPlugin, aktuell v2.5.0)
**Zielversionen:** Shopware 6.6 (ab 6.6.8) und 6.7 (Entwicklungs-Shop: 6.7.16)
**Status:** Design abgestimmt, bereit für Implementierungsplan

---

## 1. Intent – das Warum

Im Shop soll nachvollziehbar sein, **wer** welche Daten **wann** und **wie** angelegt, geändert oder gelöscht hat. Das Vorbild ist ein Git-Log: Jeder Eintrag ist ein „Commit“ mit Autor, Zeitpunkt, Art der Aktion und einem Diff (Vorher → Nachher pro Feld).

Die Funktion wird **bewusst in `muckiware/log-plugin` integriert** und nicht als eigenes Plugin gebaut. Das Plugin `emz/admin-logger` (8mylez, liegt unter `custom/plugins/EmzAdminLogger`) dient als Inspiration für den technischen Ansatz. Es wird nicht übernommen.

## 2. Ziele

1. Jede DAL-Schreiboperation (create / update / delete) auf konfigurierten Entitäten wird in einer eigenen Entity persistiert.
2. **Alle Quellen** werden geloggt: Admin-User, Integrationen/API, Storefront/Sales-Channel, CLI, Scheduled Tasks und das System.
3. Pro Eintrag werden erfasst:
   - die Entität und die Datensatz-ID
   - der Admin-User (Name und ID), **nur wenn es einen gibt**. Der User ist per `ManyToOneAssociationField` mit der Shopware-`user`-Entity verknüpft.
   - Datum und Art der Aktion
   - der Inhalt als Diff Vorher/Nachher pro Feld
   - die IP-Adresse, **nur wenn ein HTTP-Request existiert**
4. **Unterdaten** werden sichtbar mitgeloggt, also Übersetzungen, Preise, Zuordnungen/Mapping-Tabellen und andere Child-Tabellen. Sie erscheinen als Felder im Diff des **Hauptdatensatzes**, nicht als eigene Einträge.
5. **Ein Eintrag pro Speichervorgang und Hauptdatensatz** (Variante B, „Commit“). Speichert ein User ein Produkt mit neuem englischem Namen und neuem Preis, entsteht *ein* Log-Eintrag „Produkt X geändert“ mit allen Feldern im Diff.
6. Eine zentrale Admin-Ansicht zeigt eine Tabelle mit Freitextsuche und Filtern nach Datum (von–bis), User bzw. Quelle und Entität. Ein Klick klappt den Diff einer Zeile auf.
7. Auf den Detailseiten gibt es einen **Tab „Änderungsverlauf“**, der dieselben Commits gefiltert auf den aktuellen Datensatz zeigt.
8. In den Plugin-Einstellungen gibt es einen globalen Ein/Aus-Schalter für das Änderungslog und eine Auswahl der zu loggenden Hauptentitäten.
9. Ein Scheduled Task löscht Einträge, die älter als die konfigurierte Aufbewahrungsdauer sind.
10. Das Ganze läuft lauffähig auf **Shopware 6.6 und 6.7** mit derselben Codebasis.

## 3. Nicht-Ziele

- Keine Wiederherstellung bzw. kein „Revert“ aus dem Log heraus.
- Keine Übernahme bzw. Migration vorhandener Daten aus `emz_admin_log`.
- Kein Bearbeiten oder manuelles Löschen von Log-Einträgen im Admin. Das Log ist dort nur lesbar.
- Kein Logging von Lesezugriffen.
- Kein Logging von Schreibvorgängen, die am DAL vorbei per direktem SQL laufen (z. B. Indexer oder Core-Code mit `Connection`). Das ist eine technische Grenze, kein Ziel. **Ausnahme:** Die Systemkonfiguration wird über einen eigenen Weg erfasst, siehe 6.11.

## 4. Anti-Ziele (wäre ein Fehlschlag, auch wenn es technisch funktioniert)

- **Sensible Werte landen im Log.** Passwörter (auch Hashes), Schlüssel, Tokens, Secrets und Zugangsdaten werden **niemals** gespeichert, weder im Diff noch im Payload. Das Log zeigt für solche Felder nur, *dass* sie geändert wurden, z. B. `Passwort: *** → ***`.
- Ein Speichervorgang zerfällt in viele technische Einzelzeilen (`product_translation`, `product_price` …), sodass man den Zusammenhang nicht mehr erkennt.
- Eine Übersetzungs- oder Preisänderung ist im Log unsichtbar, weil nur die Haupttabelle beobachtet wird. Das ist die bekannte Schwäche von Emz.
- Das Logging bricht den Schreibvorgang ab oder macht ihn spürbar langsam. Ein Fehler beim Loggen darf das eigentliche Speichern nie verhindern.
- Das Log loggt sich selbst (Rekursion), oder die Tabelle explodiert unkontrolliert ohne Cleanup.
- Die Lösung läuft nur auf 6.7 oder nur auf 6.6.

## 5. Constraints

- Shopware 6.6.8+ und 6.7.x, PHP ≥ 8.2. Das Plugin hat CI-Workflows für beide Versionen (`.github/workflows/main-test-sw66.yml`, `main-test-sw67.yml`). Beide müssen grün sein.
- LightsOn-/Projekt-Standards gemäß `CLAUDE.md`: PSR-12, `declare(strict_types=1)`, `final`/`readonly` wo möglich, max. 30 Zeilen pro Methode und max. 4 Parameter. Neue Klassen folgen dem Stil des Plugins.
- Präfix für DB-Tabellen, Entities und Admin-Module ist `muwa_` (bestehend: `muwa_logging_event`, Admin-Modul `muwa-log-viewer`).
- Config-Keys liegen unter `MuckiLogPlugin.config.` und werden um neue Karten in `src/Resources/config/config.xml` ergänzt.
- Tests laufen im Projekt nur per `ddev exec` (PHPUnit 11). Vor Tests muss `ddev mutagen sync` laufen.
- `emz/admin-logger` ist im Entwicklungs-Shop aktuell installiert und aktiv. Zum Testen sollte es deaktiviert werden, damit nicht doppelt geloggt wird.

## 6. Entschiedener Teil des Wie

### 6.1 Erfassung (Backend)

Der Ansatz folgt Emz und ist in beiden Shopware-Versionen identisch verfügbar:

- **`PreWriteValidationEvent`:** Für jedes `ChangeSetAware`-Command (Update/Delete), das zu einer getrackten Hauptentität *oder einer ihrer Unterentitäten* gehört, wird `requestChangeSet()` aufgerufen. So liefert Shopware die Vorher-Werte.
- **`EntityWrittenContainerEvent`:** Die Write-Results werden eingesammelt, pro Hauptdatensatz gruppiert (siehe 6.2) und als Log-Einträge persistiert.
- **Rekursionsschutz:** Beim Schreiben der Log-Einträge wird ein Context-State gesetzt und im Subscriber geprüft. Die eigene Log-Entity wird zusätzlich immer übersprungen.
- **Fehlertoleranz:** Fehler beim Loggen werden abgefangen und über den Plugin-Logger (`Psr\Log\LoggerInterface`, Context `['muwa', 'admin-change-log']`) protokolliert. Sie werden nie weitergeworfen.
- Hinweis: `ChangeSetAware` ist in Shopware als `@internal` markiert. Der Mechanismus ist seit 6.4 stabil und wird von Emz genutzt. Dieses Risiko nehmen wir bewusst in Kauf, Tests auf beiden Versionen sichern es ab.

### 6.2 Gruppierung zu „Commits“ (Variante B)

- Ein Log-Eintrag entspricht **einem Hauptdatensatz innerhalb eines `EntityWrittenContainerEvent`**.
- Unterentitäten werden ihrem Hauptdatensatz zugeordnet:
  - **Übersetzungen** (`EntityTranslationDefinition`) über die Parent-Definition und die Parent-FK. Die Sprache wird am Feld vermerkt, z. B. „Name (en-GB)“.
  - **Child-Tabellen** mit FK auf den Hauptdatensatz, z. B. `product_price.product_id`.
  - **Mapping-Tabellen** (`MappingEntityDefinition`), z. B. `product_category`, als „Zuordnung hinzugefügt/entfernt“.
- Die Aktion des Eintrags ergibt sich aus dem Hauptdatensatz (angelegt/geändert/gelöscht). Wurden in einem Speichervorgang nur Unterdaten geändert, heißt der Eintrag „geändert“.
- Jedes Diff-Feld trägt eine Kennung, aus welcher Unterentität es stammt, und optional einen Kontext (Sprache, Regel, Währung, verknüpfter Datensatz). So kann die Admin-Ansicht es lesbar beschriften.
- **Mehrstufige Unterdaten** (z. B. `order_delivery_position` → `order_delivery` → `order`) werden bis zur obersten **getrackten** Hauptentität hochgerechnet. *(Meine Wahl)*
- **Varianten** sind eigene Produkte mit eigener Detailseite und bekommen deshalb eigene Commits („Variante X geändert“). Sie landen nicht als Unterdaten im Elternprodukt. *(Meine Wahl)*
- **Überschneidung:** Wenn eine Unterentität (z. B. `customer_address`) selbst als Hauptentität ausgewählt ist, wird sie trotzdem nur als Unterdaten ihrer obersten getrackten Hauptentität geloggt. Jede Änderung erscheint also genau einmal. *(Meine Wahl)*
- Insert: Vorher-Werte sind leer. Delete: Nur Vorher-Werte aus dem ChangeSet, Nachher leer, sodass man im Log sieht, *was* gelöscht wurde.

### 6.3 Quelle / Autor

Pro Eintrag werden gespeichert:

| Quelle | Erkennung | Gespeichert |
|---|---|---|
| Admin-User | `AdminApiSource` mit `userId` | `userId` (FK + `ManyToOne` zu `user`), Name als Snapshot |
| Integration | `AdminApiSource` mit `integrationId` ohne `userId` | `integrationId`, Integrationsname als Snapshot |
| Storefront / Sales Channel | `SalesChannelApiSource` | `salesChannelId`, Name als Snapshot |
| System / CLI / Task | `SystemSource` | nur Quelle „System“ |

- Den User-Namen gibt es nur, wenn ein User existiert. Er wird als Snapshot mitgespeichert, damit er nach dem Löschen des Users lesbar bleibt. Die FK auf `user` wird beim Löschen des Users auf `NULL` gesetzt (`ON DELETE SET NULL`).
- Die IP-Adresse kommt aus `RequestStack::getCurrentRequest()?->getClientIp()` und ist leer, wenn es keinen Request gibt (CLI, Task, Messenger-Worker).

### 6.4 Sensible Felder

- Nie gespeichert werden:
  - Felder vom Typ `PasswordField`
  - Felder, deren Name auf ein Secret hindeutet (z. B. `password`, `secret`, `token`, `accessKey`, `secretAccessKey`, `apiKey`, `privateKey`, `hash`)
  - für `system_config`: Werte von Keys, die einem dieser Muster entsprechen
- Solche Felder erscheinen im Diff nur als „geändert“ mit `***` auf beiden Seiten.
- Die Liste der Muster ist zentral definiert und durch Tests abgesichert.
- Persönliche Kundendaten (Name, Adresse, E-Mail) werden normal geloggt. Die Aufbewahrung regelt der Cleanup. *(Meine Wahl, siehe Abschnitt 8)*

### 6.5 Datenmodell

Neue Entity `muwa_admin_change_log` (Definition, Entity, Collection und Migration im Stil der bestehenden `muwa_logging_event`) mit sinngemäß diesen Feldern:

- `id`
- `entity_name` (Hauptentität)
- `entity_id`
- `entity_label` (lesbarer Name des Datensatzes zum Zeitpunkt der Aktion, z. B. Produktname oder Bestellnummer)
- `action` (`insert` / `update` / `delete`)
- `source_type` (`user` / `integration` / `sales_channel` / `system`)
- `user_id` (FK, nullable) und `user_name`
- `integration_id` / `sales_channel_id` (nullable) und `source_name`
- `ip_address` (nullable)
- `changes` (JSON: Liste von Feldänderungen mit Feld, Unterentität, Kontext, alt, neu)
- `created_at`

Dazu kommen die Assoziation `user` (`ManyToOneAssociationField` → `UserDefinition`) und Indizes auf `created_at`, `entity_name + entity_id` und `user_id`. Freitextsuche erfolgt über `SearchRanking` auf `entity_label`, `entity_name`, `user_name`, `source_name` und `entity_id`.

### 6.6 Plugin-Einstellungen (`config.xml`)

Neue Karte „Änderungslog“ mit:

- `activeChangeLog` (bool, Standard: aus): globaler Ein/Aus-Schalter. Er ist **unabhängig** vom bestehenden Schalter `active` (Log4php-Logging). Ein Hinweis im Hilfetext erklärt: Fehler des Änderungslogs selbst landen nur dann in einer Log-Datei, wenn `active` an ist. *(Meine Wahl)*
- `changeLogEntities`: Auswahl der Hauptentitäten
- `changeLogRetentionDays` (int, Standard: 90, `0` = nie löschen)

### 6.7 Admin-Ansicht

- Neues Admin-Modul unter **Einstellungen › Plugins** (wie der bestehende `muwa-log-viewer`): „Änderungslog“.
- Eine Tabelle zeigt die Spalten Datum, Aktion (Badge), Entität, Datensatz, Wer (mit Quelle als Untertitel), IP und Anzahl der Änderungen.
- Oben gibt es Freitextsuche, einen Datumsbereich (von–bis), einen Filter für User/Quelle (Admin-User, Integrationen, Sales Channels, „System“) und einen Filter für Entität.
- Ein Klick auf eine Zeile klappt den Diff im Git-Stil auf (rot `−` alt, grün `+` neu, Feldbeschriftung inkl. Kontext wie „Name (en-GB)“ oder „Preis brutto (Regel: B2B, EUR)“).
- Die Tabelle hat Pagination und ist standardmäßig nach Datum absteigend sortiert.
- Als Referenz dient das abgestimmte Mockup „Variante B“ in `.superpowers/brainstorm/…/content/log-list.html` im Projekt-Root.

### 6.8 Tab „Änderungsverlauf“ auf Detailseiten

- Der Tab kommt auf die Detailseiten Produkt, Kategorie, Bestellung und Kunde.
- Erlebniswelten (`sw-cms-detail`) sind ein Layout-Designer ohne Tabs. Dort kommt der Verlauf als eigener Eintrag in die Sidebar des Designers. Die Listen- und Diff-Komponente muss deshalb auch in einer schmalen Spalte funktionieren. *(Meine Wahl)*
- Er nutzt dieselbe Listen- und Diff-Komponente wie das zentrale Modul, gefiltert auf `entity_name` + `entity_id`.
- Er ist nur sichtbar, wenn das Änderungslog aktiv ist, die Entität getrackt wird und der User die Berechtigung hat.

### 6.9 Berechtigung (ACL)

- Es gibt eine eigene Berechtigung „Änderungslog ansehen“, die analog zu `muwa_log_viewer.viewer` registriert wird.
- Sie schützt sowohl das zentrale Modul als auch die Tabs.
- Im Admin ist das Log nur lesbar.

### 6.10 Cleanup

- Ein neuer Scheduled Task (im bestehenden `schedule.xml`) läuft einmal täglich.
- Er löscht Einträge mit `created_at` älter als `changeLogRetentionDays` per DBAL in Blöcken (z. B. 1000 pro Statement), damit die Tabelle nicht lange gesperrt wird.
- Bei `0` oder deaktiviertem Änderungslog passiert nichts.

### 6.11 Systemkonfiguration (Plugin- und Shop-Einstellungen)

Vom User ausdrücklich gewünscht: Änderungen an Einstellungen werden mitgeloggt.

- **Problem:** `SystemConfigService::set()` und `setMultiple()` schreiben per `Connection` direkt in die Datenbank, also am DAL vorbei. Der Mechanismus aus 6.1 sieht diese Schreibvorgänge deshalb nicht.
- **Zweiter Erfassungsweg:** Ein eigener Subscriber hört auf `BeforeSystemConfigMultipleChangedEvent` und `BeforeSystemConfigChangedEvent`, liest die Vorher-Werte selbst und bildet daraus Vorher → Nachher. Die DAL-Schreibvorgänge auf `system_config` werden im Weg aus 6.1 ignoriert, damit nichts doppelt erscheint.
- **Gruppierung:** Ein Commit entspricht einem Speichervorgang, also einem `setMultiple`-Aufruf bzw. einem `set`, je Domain (z. B. `MuckiLogPlugin.config`) und Sales Channel. Hauptdatensatz ist die Domain. `entity_name` ist `system_config`, `entity_label` ist z. B. „Einstellungen MuckiLogPlugin (Alle Verkaufskanäle)“.
- Unveränderte Keys erscheinen nicht im Diff.
- Secrets werden nach den Regeln aus 6.4 maskiert.
- `system_config` steht in der Entitäten-Auswahl als „Einstellungen“ zur Verfügung.
- Ob die Events und ihre Signaturen in 6.6.8 identisch sind, muss der Builder prüfen. In 6.7.16 sind alle vier Events vorhanden.
- In der zentralen Liste reicht die Anzeige. Ein Tab auf einer Einstellungsseite ist nicht nötig.

## 7. Dem Builder überlassen

- Konkrete Klassen- und Dateinamen sowie die Aufteilung in Services (Subscriber, Collector/Grouper, Sanitizer, Persister, Source-Resolver …)
- Das exakte JSON-Format von `changes`, solange es Feld, Unterentität, Kontext, alt und neu abdeckt
- Wie die Zuordnung „Unterentität → Hauptentität“ generisch aus den Definitionen abgeleitet wird
- Wie `entity_label` je Entität ermittelt wird (z. B. `name`, `title`, `orderNumber`, `customerNumber`, Fallback ID)
- Wie Werte im Diff formatiert werden (Booleans, IDs zu Namen, Preise)
- Die UI-Komponente für die Entitäten-Auswahl in den Einstellungen
- Wie Version-Writes (z. B. Bestellbearbeitung im Admin über Draft-Versionen) behandelt werden. Wichtig ist nur: Am Ende sieht man genau einen sinnvollen Eintrag für die tatsächlich übernommene Änderung, nicht die Zwischenschritte im Draft.
- Snippets de-DE und en-GB, README- und CHANGELOG-Eintrag sowie der Version-Bump

## 8. Vom Assistenten getroffene Entscheidungen (bitte prüfen)

Diese Punkte wurden nicht ausdrücklich vom User entschieden, sondern als sinnvolle Defaults gesetzt:

1. Der Tab „Änderungsverlauf“ kommt zunächst auf die Seiten Produkt, Kategorie, Bestellung, Kunde und CMS-Seite.
2. Persönliche Kundendaten werden normal geloggt und nur über den Cleanup begrenzt.
3. Die Aufbewahrung beträgt standardmäßig 90 Tage, `0` bedeutet „nie löschen“.
4. In den Einstellungen sind alle Entitäten des Shops wählbar. Produkt, Kategorie, Bestellung, Kunde und CMS-Seite sind vorausgewählt.
5. Es gibt eine eigene ACL-Berechtigung, und das Log ist im Admin nur lesbar.
6. Emz-Daten werden nicht migriert, Emz wird nach Inbetriebnahme deaktiviert.
7. Den Integrations- bzw. Sales-Channel-Namen speichern wir als Snapshot, analog zum User-Namen.
8. Das Änderungslog ist nach der Installation standardmäßig **aus**.
9. Mehrstufige Unterdaten werden bis zur obersten getrackten Hauptentität hochgerechnet. Jede Änderung erscheint genau einmal.
10. Varianten bekommen eigene Commits.
11. Bei Erlebniswelten erscheint der Verlauf in der Sidebar des Designers statt als Tab.
12. `activeChangeLog` ist unabhängig vom Log4php-Schalter `active`.
13. Einstellungsänderungen werden zu einem Commit je Speichervorgang, Domain und Sales Channel zusammengefasst. Für Einstellungsseiten gibt es keinen Tab.
