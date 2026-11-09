# Admin-Änderungslog Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Das Plugin protokolliert jede DAL-Schreiboperation und jede Änderung an der Systemkonfiguration als Git-artigen „Commit“ (Autor, Zeit, Aktion, Vorher/Nachher-Diff) in einer eigenen Entity. Die Einträge sind in einer Admin-Liste und als Tab „Änderungsverlauf“ auf Detailseiten einsehbar.

**Architecture:** Ein Subscriber fordert über `PreWriteValidationEvent` für relevante Commands ChangeSets an. Ein zweiter Schritt sammelt im `EntityWrittenContainerEvent` die Write-Results, ordnet Unterentitäten (Übersetzung, Child, Mapping) über einen aus den Definitionen abgeleiteten Graphen ihrem obersten getrackten Hauptdatensatz zu und schreibt pro Hauptdatensatz einen Eintrag nach `muwa_admin_change_log`. Systemkonfiguration wird separat über `BeforeSystemConfigMultipleChangedEvent` erfasst. Das Admin-Frontend nutzt die Entity direkt über das Repository (keine eigene API) und teilt eine Listen- und Diff-Komponente zwischen zentralem Modul und Detail-Tabs.

**Tech Stack:** PHP 8.2, Shopware 6.6.8+ und 6.7 (DAL, Scheduled Tasks, Admin mit Vue 3 und klassischen `sw-*`-Komponenten), PHPUnit 11 mit dem Shopware-Test-Bootstrap des Plugins.

**Spec:** `docs/superpowers/specs/2026-11-03-admin-change-log-design.md`. Der Plan argumentiert aus dem Spec, beide gehören zusammen gelesen.

## Global Constraints

- **Versionen:** Shopware **6.6 (ab 6.6.8) und 6.7**, eine Codebasis. Es sind nur Core-APIs erlaubt, die in beiden Versionen existieren. Im Admin ausschließlich klassische `sw-*`-Komponenten, kein `mt-*`. `composer.json` bleibt bei `"shopware/core": ">= v6.6.8.0"` und `"php": ">= 8.2"`.
- **Stil:**
  - `declare(strict_types=1);`, PSR-12, `final` und `readonly` wo möglich
  - max. 30 Zeilen pro Methode, max. 4 Parameter
  - Namespace `MuckiLogPlugin\`
  - `services.xml` mit Tabs eingerückt, wie im bestehenden Plugin
- **Namen:**
  - Entity bzw. Tabelle `muwa_admin_change_log`
  - Admin-Modul `muwa-change-log`
  - ACL-Key `muwa_change_log` mit Rolle `viewer`
  - Config-Keys `MuckiLogPlugin.config.activeChangeLog`, `MuckiLogPlugin.config.changeLogEntities`, `MuckiLogPlugin.config.changeLogRetentionDays`
- **Defaults:**
  - `activeChangeLog` = aus
  - `changeLogEntities` = `["product","category","order","customer","cms_page"]`
  - `changeLogRetentionDays` = `90`, `0` heißt nie löschen
- **Nie speichern:** Passwörter (auch Hashes), Schlüssel, Tokens und Secrets. Maskiert wird mit dem Literal `***` auf beiden Seiten.
- **Fehlertoleranz:** Kein Fehler des Änderungslogs darf den eigentlichen Schreibvorgang abbrechen. Fehler werden als `Throwable` gefangen und mit `$logger->error($msg, ['muwa', 'admin-change-log'])` geloggt.
- **Tests (aus dem Shop-Root `/Users/torstenfreyda/shopdev/sw6/sw67`):** Vor jedem Lauf `ddev mutagen sync` ausführen, da Host-Edits sonst verzögert im Container ankommen. Dann:
  `ddev exec "./vendor/bin/phpunit --configuration=custom/static-plugins/MuckiLogPlugin --filter <Name>"`
  PHPUnit 11 läuft, Data Provider müssen also `static` sein. Testklassen liegen unter `tests/ChangeLog/…` im Namespace `MuckiLogPlugin\tests\ChangeLog\…`. Integrationstests nutzen `Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour`.
- **Admin-Build:** `ddev exec bin/build-administration.sh && ddev exec bin/console assets:install`
- **Vor dem Testen** im Dev-Shop `EmzAdminLogger` deaktivieren: `ddev exec bin/console plugin:deactivate EmzAdminLogger`
- **Git:**
  - Branch-Format `feat/<JIRA-Ticket> - admin-change-log`
  - Commit-Format `feat:` / `test:` / `docs:` …
  - **Vor dem ersten Commit beim User nachfragen**, ob pro Task committet werden soll.
  - Commit-Trailer: `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`

## Review Focus

1. **Massen-Writes per Sync-API oder ERP-Import:** 50 Produkte in einem `upsert` ergeben 50 Commits. Sie werden mit einem einzigen `create()`-Aufruf geschrieben, und Label bzw. Source werden nicht pro Datensatz neu geladen. → Test in Task 7.
2. **Reines Übersetzungs-Update:** Das Produkt hat keine direkte Spaltenänderung, nur `product_translation` ändert sich. Trotzdem entsteht ein Commit am Produkt mit Vorher- und Nachher-Wert. Genau das ist die Emz-Schwäche. → Test in Task 7.
3. **Schreiben ohne HTTP-Request** (CLI, Messenger-Worker, Scheduled Task): Quelle ist `system`, die IP ist `null`, es wird keine Exception geworfen. → Test in Task 5.
4. **Admin-User wird später gelöscht:** Der Log-Eintrag bleibt, `user_id` wird `NULL`, `user_name` bleibt lesbar. → Test in Task 1.
5. **Nicht-skalare Rohwerte** (Binary-IDs mit 16 Bytes, JSON-Preise als String, Resources) landen JSON-serialisierbar und lesbar im Diff. Binary wird zu Hex, JSON-Strings werden decodiert. → Test in Task 3.

---

## File Structure

**Neu – Backend**
- `src/Core/AdminChangeLog/AdminChangeLogDefinition.php`, `AdminChangeLogEntity.php`, `AdminChangeLogCollection.php`: die Entity
- `src/Migration/Migration1793724000AdminChangeLog.php`: die Tabelle
- `src/ChangeLog/ChangeLogConfig.php`: Lesen der drei Config-Keys
- `src/ChangeLog/Struct/FieldChange.php`, `RootReference.php`, `ChangeSource.php`, `ChangeLogCommit.php`, `ChangeAction.php` (enum), `ChangeSourceType.php` (enum)
- `src/ChangeLog/SensitiveFieldMasker.php`: Erkennen und Maskieren von Secrets
- `src/ChangeLog/ValueNormalizer.php`: Rohwerte in JSON-taugliche Werte überführen
- `src/ChangeLog/FieldChangeExtractor.php`: `EntityWriteResult` → `list<FieldChange>`
- `src/ChangeLog/EntityGraph.php` + `Struct/ParentLink.php`: Eltern-Beziehungen aus den Definitionen
- `src/ChangeLog/RootResolver.php`: Write-Result → oberster getrackter Hauptdatensatz
- `src/ChangeLog/CommitBuilder.php`: Gruppierung zu Commits
- `src/ChangeLog/SourceResolver.php`: Context + Request → `ChangeSource`
- `src/ChangeLog/EntityLabelResolver.php`: lesbarer Name des Hauptdatensatzes
- `src/ChangeLog/ChangeLogWriter.php`: Persistieren mit Rekursionsschutz und Fehlertoleranz
- `src/Subscriber/ChangeLogWriteSubscriber.php`: DAL-Erfassung
- `src/Subscriber/SystemConfigChangeSubscriber.php`: Erfassung der Systemkonfiguration
- `src/Schedules/CleanupChangeLogTask.php`, `CleanupChangeLogTaskHandler.php`

**Geändert – Backend**
- `src/Core/ConfigPath.php`: drei neue Cases
- `src/Resources/config/config.xml`: neue Karte
- `src/Resources/config/services.xml`, `schedule.xml`: Registrierungen

**Neu – Admin** (`src/Resources/app/administration/src/`)
- `module/muwa-change-log/index.js`: Modul mit `settingsItem` und Route `index`
- `module/muwa-change-log/page/muwa-change-log-index/`: die zentrale Seite
- `module/muwa-change-log/component/muwa-change-log-list/`: Filter, Tabelle und Pagination, wiederverwendbar
- `module/muwa-change-log/component/muwa-change-log-diff/`: Git-Diff einer Zeile
- `module/muwa-change-log/component/muwa-change-log-entity-select/`: Entitäten-Auswahl in `config.xml`
- `module/muwa-change-log/extension/`: Tabs für `sw-product-detail`, `sw-category-detail`, `sw-order-detail` und `sw-customer-detail`, Sidebar für `sw-cms-detail`
- `module/muwa-change-log/snippet/de-DE.json`, `en-GB.json`

**Geändert – Admin**
- `main.js`: Import und ACL-Mapping

**Tests:** `tests/ChangeLog/…`, eine Datei pro Klasse (Namen in den Tasks)

---

### Task 1: Datenmodell `muwa_admin_change_log`

**Files:**
- Create: `src/Core/AdminChangeLog/AdminChangeLogDefinition.php`, `AdminChangeLogEntity.php`, `AdminChangeLogCollection.php`
- Create: `src/Migration/Migration1793724000AdminChangeLog.php`
- Modify: `src/Resources/config/services.xml` (Tag `shopware.entity.definition`, `entity="muwa_admin_change_log"`)
- Test: `tests/ChangeLog/AdminChangeLogDefinitionTest.php`

**Interfaces:**
- Produces:
  - `AdminChangeLogDefinition::ENTITY_NAME = 'muwa_admin_change_log'` und das Repository `muwa_admin_change_log.repository`
  - Property-Namen: `id`, `entityName`, `entityId`, `entityLabel`, `action`, `sourceType`, `userId`, `userName`, `integrationId`, `salesChannelId`, `sourceName`, `ipAddress`, `changes` (Json), `createdAt`, Assoziation `user`

- [ ] **Step 1: Write the failing test.** Die Testklasse nutzt `IntegrationTestBehaviour`.
  - `testCreateAndReadWithUserAssociation`: Legt einen User an und schreibt einen Eintrag mit `userId`, `changes = [['field' => 'name', 'old' => 'a', 'new' => 'b']]`. Lesen mit `Criteria` + `addAssociation('user')` ergibt `getUser()->getId() === $userId` und `getChanges()[0]['new'] === 'b'`.
  - `testUserDeletionKeepsEntryAndName`: Nach `user.repository->delete` ist der Eintrag noch da, `getUserId() === null` und `getUserName() === 'Max Admin'`.
  - `testTermSearchFindsByLabelAndUserName`: `Criteria::setTerm('Rotes')` findet den Eintrag mit `entityLabel = 'Rotes T-Shirt'`.
- [ ] **Step 2: Run, expect FAIL** (`Definition for entity "muwa_admin_change_log" does not exist`).
- [ ] **Step 3: Implement the definition and migration.**
  - Felder gemäß Spec 6.5, `StringField`-Längen: `entity_name` 64, `entity_id` 255, `entity_label` 255, `action` 16, `source_type` 16, `user_name` 255, `source_name` 255, `ip_address` 45.
  - `FkField user_id → UserDefinition`, nullable, `ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false)`, `JsonField changes`, `CreatedAtField`, `UpdatedAtField`.
  - `IdField integration_id`, `IdField sales_channel_id` ohne FK, damit nichts bricht, wenn die Integration gelöscht wird.
  - `SearchRanking` auf `entityLabel` (HIGH), `entityName`, `userName`, `sourceName`, `entityId` (MIDDLE).
  - Die Migration legt die Tabelle mit `FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE SET NULL` an, dazu Indizes `idx_created_at (created_at)`, `idx_entity (entity_name, entity_id)` und `idx_user (user_id)`.
  - Die Entity bekommt **keine** Reverse-Assoziation am Core-`user`, also keine `EntityExtension`.
- [ ] **Step 4: Run, expect PASS.** Vorher `ddev exec bin/console plugin:update MuckiLogPlugin` ausführen, damit die Migration im Dev-Shop läuft.
- [ ] **Step 5: Commit** (`feat: add muwa_admin_change_log entity`), nach Rückfrage.

### Task 2: Konfiguration

**Files:**
- Create: `src/ChangeLog/ChangeLogConfig.php`
- Modify: `src/Core/ConfigPath.php` mit den Cases `CONFIG_PATH_ACTIVE_CHANGE_LOG`, `CONFIG_PATH_CHANGE_LOG_ENTITIES` und `CONFIG_PATH_CHANGE_LOG_RETENTION_DAYS` (Werte siehe Global Constraints)
- Modify: `src/Resources/config/config.xml` mit einer neuen `<card>`:
  - `<title>Admin change log</title>` / `<title lang="de-DE">Änderungslog</title>`
  - `bool activeChangeLog`. Der Hilfetext sagt sinngemäß: „Fehler des Änderungslogs werden nur in eine Log-Datei geschrieben, wenn ‚Active' (oben) eingeschaltet ist.“
  - `<component name="muwa-change-log-entity-select"><name>changeLogEntities</name>…</component>` mit dem Default-JSON
  - `int changeLogRetentionDays` mit Default 90
  - Die Komponente selbst entsteht erst in Task 11. Bis dahin fehlt das Feld in der Settings-Seite, das ist so gewollt.
- Test: `tests/ChangeLog/ChangeLogConfigTest.php` als Unit-Test mit gemocktem `SystemConfigService`, wie in `tests/Services/SettingsTest.php`

**Interfaces:**
- Produces: `final class ChangeLogConfig { isActive(): bool; getTrackedEntities(): list<string>; getRetentionDays(): int }`

- [ ] **Step 1: Failing tests.** Der Data Provider ist `static`.
  - `testTrackedEntitiesDefaultWhenUnset`: Ist der Wert `null`, kommen die fünf Default-Entitäten zurück.
  - `testTrackedEntitiesFiltersNonStrings`: `['product', 5, '', 'order']` ergibt `['product','order']`.
  - `testRetentionDays`: `null` → 90, `'30'` → 30, `-5` → 0.
  - `testIsActive`: Der Wert kommt durch.
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.** `getTrackedEntities()` entfernt `muwa_admin_change_log` immer aus der Liste.
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: add change log settings`).

### Task 3: Feldänderungen extrahieren, normalisieren, maskieren

**Files:**
- Create: `src/ChangeLog/Struct/FieldChange.php`, `ChangeAction.php`
- Create: `src/ChangeLog/ValueNormalizer.php`, `SensitiveFieldMasker.php`, `FieldChangeExtractor.php`
- Test: `tests/ChangeLog/ValueNormalizerTest.php`, `SensitiveFieldMaskerTest.php`, `FieldChangeExtractorTest.php`

**Interfaces:**
- Produces:
  - `enum ChangeAction: string { case Insert = 'insert'; case Update = 'update'; case Delete = 'delete'; static fromOperation(string $op): self }` (bildet `EntityWriteResult::OPERATION_*` ab)
  - `final readonly class FieldChange(string $field, ?string $subEntity, array $context, mixed $old, mixed $new, bool $masked = false) { toArray(): array{field:string, subEntity:?string, context:array, old:mixed, new:mixed, masked:bool} }`
  - `final class ValueNormalizer { normalize(mixed $value): mixed }`
  - `final class SensitiveFieldMasker { public const MASK = '***'; isSensitive(string $fieldName, ?Field $definitionField = null): bool }`
  - `final class FieldChangeExtractor(DefinitionInstanceRegistry, ValueNormalizer, SensitiveFieldMasker) { extract(EntityWriteResult $result, ?string $subEntity, array $context): list<FieldChange> }`

- [ ] **Step 1: Failing tests.**
  - **ValueNormalizer:**
    - Ein 16-Byte-Binary wird zu einem 32-stelligen Hex-String in Kleinbuchstaben.
    - Der String `'{"a":1}'` wird zu `['a' => 1]`.
    - Eine Resource wird zum Inhalt der Resource.
    - Ein `DateTimeInterface` wird zu `format(DATE_ATOM)`.
    - Skalare Werte bleiben unverändert.
    - Das Ergebnis ist immer `json_encode`-fähig. Dafür gibt es einen Test mit `JSON_THROW_ON_ERROR`.
  - **SensitiveFieldMasker** (`static` Provider):
    - Diese Namen sind sensibel: `password`, `newPassword`, `legacy_password`, `secretAccessKey`, `secret_access_key`, `apiKey`, `accessKey`, `privateKey`, `token`, `refreshToken`, `hash`, `passwordHash`.
    - Diese Namen sind nicht sensibel: `name`, `description`, `stock`.
    - Ein beliebiges Feld mit `PasswordField` als Definition-Feld ist immer sensibel.
    - Verglichen wird case-insensitiv nach Entfernen von `_`, und zwar per Substring gegen `['password','secret','token','accesskey','apikey','privatekey','hash']`.
  - **FieldChangeExtractor** (Integration, nutzt die echte Registry):
    - `testInsertUsesPayloadAsNew`: Ein Insert-Result `product` mit `{id, stock: 5, productNumber: 'X'}` ergibt Changes für `stock` und `productNumber` mit `old = null`. Die Felder `id`, `versionId`, `createdAt` und `updatedAt` fehlen.
    - `testUpdateUsesChangeSetAndSkipsUnchanged`: Der ChangeSet hat before `{stock: 5, active: 1}` und after `{stock: 7}`. Das ergibt genau eine Änderung `stock 5 → 7`. Feldnamen sind **Property-Namen**: Storage-Namen wie `product_number` werden über `$definition->getFields()->getByStorageName()` übersetzt.
    - `testUpdateWithoutChangeSetFallsBackToPayload`: ergibt `old = null`.
    - `testDeleteUsesChangeSetBefore`: ergibt `new = null` für alle Before-Spalten.
    - `testSensitiveFieldIsMasked`: Ein `user`-Update mit `password` ergibt `old = '***'`, `new = '***'` und `masked = true`.
    - `testSubEntityAndContextArePassedThrough`.
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.** Felder, deren Storage-Name eine FK auf den Hauptdatensatz ist (z. B. `product_id` in `product_price`), sind Kontext und werden übersprungen. Der Extractor bekommt sie nicht übergeben, er überspringt nur `id`, `version_id` und alle `*_version_id`, `created_at` und `updated_at`.
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: extract masked field changes from write results`).

### Task 4: Entity-Graph und Root-Auflösung

**Files:**
- Create: `src/ChangeLog/Struct/ParentLink.php`, `RootReference.php`, `src/ChangeLog/EntityGraph.php`, `RootResolver.php`
- Test: `tests/ChangeLog/EntityGraphTest.php`, `RootResolverTest.php` (Integration)

**Interfaces:**
- Produces:
  - `final readonly class ParentLink(string $parentEntity, string $fkStorageName, string $fkPropertyName, string $kind)`, wobei `$kind` einer der Werte `'translation' | 'child' | 'mapping'` ist
  - `final readonly class RootReference(string $entityName, string $entityId, ?string $subEntity, array $context)`
  - `final class EntityGraph(DefinitionInstanceRegistry) { getParentLinks(string $entity): list<ParentLink>; isRelevant(string $entity, list<string> $tracked): bool }`
  - `final class RootResolver(EntityGraph, Connection) { resolve(EntityWriteResult $result, list<string> $tracked): ?RootReference }`

**Regeln für den Graphen** (aus Spec 6.2 abgeleitet; der Builder setzt sie ohne weitere Wahl um):
- **translation:** Das Entity ist eine `EntityTranslationDefinition`. Elternteil ist `getParentDefinition()`. Die FK ist `<parent>_id`, der Kontext ist `['languageId' => …]`.
- **child:** Ein Elternteil A hat ein `OneToManyAssociationField` (nicht `ChildrenAssociationField`, nicht A === B) auf B mit `CascadeDelete` und `isCloneRelevant() === true`. Die FK ist die `referenceField` der Assoziation, der Kontext ist `['id' => childId]`. Dadurch fallen `product_search_keyword` und `product_review` heraus (`CascadeDelete(false)`), Varianten ebenfalls.
- **mapping:** Das Entity ist eine `MappingEntityDefinition`. Jede `FkField`, deren Referenz getrackt oder relevant ist, wird zum Elternteil. Gewählt wird **die erste in Definitionsreihenfolge**, damit eine Zuordnung genau einmal erscheint. Der Kontext ist `['linkedEntity' => andere Seite, 'linkedId' => …]`.
- `isRelevant`: Das Entity selbst ist getrackt, oder irgendein Pfad über `getParentLinks` erreicht ein getracktes Entity. `system_config` und `muwa_admin_change_log` sind nie relevant. Das Ergebnis wird pro Entity gecacht.
- `resolve`: Der Pfad wird bis zum **obersten** getrackten Vorfahren hochgelaufen. Ist das Entity selbst getrackt und hat keinen getrackten Vorfahren, ist es selbst die Root mit `subEntity = null`.
  - FK-Werte kommen aus dem Payload (Property-Name) oder dem ChangeSet-Before (Storage-Name).
  - Für weitere Ebenen gilt: `SELECT LOWER(HEX(<fk>)) FROM <parent_table> WHERE id = UNHEX(:id)` per `Connection`, gecacht pro Instanz.
  - Ist keine FK auflösbar, kommt `null` zurück und das Result wird verworfen.

- [ ] **Step 1: Failing tests** (getrackt: `product`, `order`, `customer`, `customer_address`, `category`):
  - `product_translation` → `RootReference('product', $id, 'product_translation', ['languageId' => …])`
  - `product_price` → `product`, `subEntity = 'product_price'`
  - `product_category` → `product`, Kontext `linkedEntity = 'category'`
  - `order_delivery_position` → `order` über zwei Ebenen mit DB-Lookup. Dafür wird eine Bestellung per Fixture angelegt.
  - `customer_address` (selbst getrackt) → `customer`
  - Ein Varianten-`product` mit `parentId` → es selbst, `subEntity = null`
  - `product_search_keyword` → `isRelevant` ist `false`
  - Ein nicht getracktes Entity, z. B. `tax` → `null`
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: resolve sub entities to tracked root`).

### Task 5: Quelle und Label

**Files:**
- Create: `src/ChangeLog/Struct/ChangeSource.php`, `ChangeSourceType.php`, `src/ChangeLog/SourceResolver.php`, `EntityLabelResolver.php`
- Test: `tests/ChangeLog/SourceResolverTest.php`, `EntityLabelResolverTest.php`

**Interfaces:**
- Produces:
  - `enum ChangeSourceType: string { User='user'; Integration='integration'; SalesChannel='sales_channel'; System='system' }`
  - `final readonly class ChangeSource(ChangeSourceType $type, ?string $userId, ?string $userName, ?string $integrationId, ?string $salesChannelId, ?string $sourceName, ?string $ipAddress)`
  - `final class SourceResolver(EntityRepository $userRepo, EntityRepository $integrationRepo, EntityRepository $salesChannelRepo, RequestStack) { resolve(Context $context): ChangeSource }` mit Namens-Cache pro Instanz
  - `final class EntityLabelResolver(DefinitionInstanceRegistry, Connection) { resolveMany(string $entityName, array<string,array> $beforeValuesById, Context $context): array<string,string> }`

- [ ] **Step 1: Failing tests.**
  - `AdminApiSource($userId)`: Typ ist `User`, `userName` ist „Vorname Nachname“, Fallback auf `username`.
  - `AdminApiSource(null, $integrationId)`: Typ ist `Integration`, `sourceName` ist das Integration-`label`.
  - `SalesChannelApiSource`: Typ ist `SalesChannel`, `sourceName` ist der Sales-Channel-Name.
  - `SystemSource` ohne Request (leerer `RequestStack`): Typ ist `System`, `ipAddress === null` und es wird nichts geworfen. Das ist **Review Focus 3**.
  - Mit Request `REMOTE_ADDR = 192.168.1.20` ist `ipAddress = '192.168.1.20'`.
  - Der Benutzername wird bei zwei Aufrufen nur einmal geladen. Geprüft wird das mit einem Repository-Mock und `expects(once())`.
  - **Label:**
    - `product` → `name` aus der Übersetzung des Default-Contexts
    - `order` → `orderNumber`
    - `customer` → `firstName lastName (customerNumber)`
    - `cms_page` → `name`
    - Unbekannt oder gelöscht → zuerst Before-Werte `name`/`title`, sonst die ID
    - `resolveMany` lädt alle IDs eines Entity-Typs mit **einer** Abfrage
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.** Die genaue Feldreihenfolge der Labels ist Builder-Wahl laut Spec 7, solange die Tests grün sind.
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: resolve change source and entity labels`).

### Task 6: Commits bauen

**Files:**
- Create: `src/ChangeLog/Struct/ChangeLogCommit.php`, `src/ChangeLog/CommitBuilder.php`
- Test: `tests/ChangeLog/CommitBuilderTest.php` (Unit)

**Interfaces:**
- Produces:
  - `final class ChangeLogCommit { __construct(string $entityName, string $entityId); getAction(): ChangeAction; getChanges(): list<FieldChange>; getBeforeValues(): array }`
  - `final class CommitBuilder { add(RootReference $root, ChangeAction $action, list<FieldChange> $changes, array $rootBeforeValues = []): void; build(): list<ChangeLogCommit> }`

- [ ] **Step 1: Failing tests.**
  - Drei `add`-Aufrufe für dieselbe Root (`product` update, `product_translation` update, `product_price` insert) ergeben **einen** Commit mit allen Changes und der Aktion `Update`.
  - Gibt es nur Sub-Entity-Änderungen, ist die Aktion `Update`.
  - Ist die Root selbst `Insert`, gewinnt `Insert` gegenüber den Sub-Updates.
  - Ist die Root `Delete`, gewinnt `Delete`. Cascade-gelöschte Children erzeugen dann keinen zweiten Commit.
  - Zwei Roots ergeben zwei Commits in Einfügereihenfolge.
  - Ein Commit ohne Changes und ohne Insert/Delete wird verworfen.
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: group write results into change log commits`).

### Task 7: Writer und DAL-Subscriber (Ende-zu-Ende)

**Files:**
- Create: `src/ChangeLog/ChangeLogWriter.php`, `src/Subscriber/ChangeLogWriteSubscriber.php`
- Modify: `src/Resources/config/services.xml` mit allen Services aus Task 2 bis 7. Der Subscriber bekommt den Tag `kernel.event_subscriber`, der Writer `Psr\Log\LoggerInterface`.
- Test: `tests/ChangeLog/ChangeLogWriteSubscriberTest.php` (Integration)

**Interfaces:**
- Consumes: alles aus Task 2 bis 6
- Produces:
  - `final class ChangeLogWriter(EntityRepository $changeLogRepo, EntityLabelResolver, LoggerInterface) { public const CONTEXT_STATE_SKIP = 'muwa_change_log.skip'; write(list<ChangeLogCommit> $commits, ChangeSource $source, Context $context): void }`
  - Subscriber auf `PreWriteValidationEvent` (`requestChangeSet` für alle `ChangeSetAware`-Commands, deren Entity `EntityGraph::isRelevant` ist) und `EntityWrittenContainerEvent`

- [ ] **Step 1: Failing tests.** Das Setup aktiviert das Änderungslog per `SystemConfigService`, trackt `product`, legt ein Produkt an und arbeitet mit einem Context mit `AdminApiSource($userId)`.
  - `testProductNameEnAndPriceGiveOneCommit`: Ein Update mit `translations.en-GB.name` und `price` ergibt genau 1 Eintrag (`entityName = 'product'`, `action = 'update'`, `sourceType = 'user'`, `userId`). `changes` enthält einen Eintrag `name` mit `subEntity = 'product_translation'`, Kontext `languageId` en-GB und Vorher/Nachher, außerdem einen Eintrag `price`.
  - `testTranslationOnlyUpdateHasOldValue`: Wird nur der Name geändert, gibt es 1 Commit und `old` ist der alte Name, nicht `null`. **Review Focus 2.**
  - `testBulkUpsert50Products`: Ergibt 50 Einträge. Der Repository-Spy des Writers wird genau einmal mit `create()` aufgerufen. **Review Focus 1.**
  - `testDeleteLogsBeforeValues`: Ein Delete ergibt einen Commit `delete`, der `name` als `old` enthält und keine weiteren Commits für Children erzeugt.
  - `testInactiveLogsNothing`, `testUntrackedEntityLogsNothing`.
  - `testDraftVersionWriteIsSkipped`: Ein Write mit `$context->createWithVersionId($draft)` erzeugt keinen Eintrag.
  - `testOwnEntityIsNotLoggedRecursively`.
  - `testWriterFailureDoesNotBreakWrite`: Wirft das Repository des Writers, geht das Produkt-Update trotzdem durch und der Logger-Mock bekommt `error`.
  - `testUserPasswordIsMasked`: Ist `user` getrackt, ergibt ein Passwort-Update `***`.
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.**
  - Im Subscriber bricht `EntityWrittenContainerEvent` früh ab, wenn der Context-State `SKIP` gesetzt ist, `!isActive()` gilt, `$context->getVersionId() !== Defaults::LIVE_VERSION` gilt oder es keine getrackten Entitäten gibt.
  - Danach geht es über alle `EntityWrittenEvent` (ohne `system_config` und die eigene Entity) und alle Write-Results durch `RootResolver`, `FieldChangeExtractor` und `CommitBuilder`. Das Ergebnis geht an `SourceResolver::resolve` und `ChangeLogWriter::write`.
  - Die gesamte Methode steckt in `try/catch (\Throwable)`.
  - Der Writer schreibt mit `Context::createDefaultContext()`, setzt `addState(SKIP)` und speichert alle Commits mit **einem** `create()`.
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: log DAL writes as change log commits`).

### Task 8: Systemkonfiguration

**Files:**
- Create: `src/Subscriber/SystemConfigChangeSubscriber.php`
- Test: `tests/ChangeLog/SystemConfigChangeSubscriberTest.php` (Integration)

**Interfaces:**
- Consumes: `ChangeLogConfig`, `SensitiveFieldMasker`, `SourceResolver`, `ChangeLogWriter`, `ChangeLogCommit`
- Produces: Subscriber auf **nur** `BeforeSystemConfigMultipleChangedEvent` mit Priorität `-1000`. In 6.7 ruft `set()` intern `setMultiple()` auf und das Event kommt genau einmal pro Speichervorgang.

- [ ] **Step 1: Failing tests** (getrackt: `system_config`):
  - `setMultiple(['MuckiLogPlugin.config.level' => 'debug', 'MuckiLogPlugin.config.maxfilesize' => '6'])` mit unverändertem `maxfilesize` ergibt 1 Eintrag:
    - `entityName = 'system_config'`
    - `entityId = 'MuckiLogPlugin.config|global'`
    - `entityLabel = 'Einstellungen MuckiLogPlugin.config (Alle Verkaufskanäle)'`
    - genau eine Änderung `level info → debug`
  - Zwei Domains in einem Aufruf ergeben 2 Einträge.
  - Mit `salesChannelId` wird `entityId = '<domain>|<salesChannelId>'` und das Label enthält den Sales-Channel-Namen.
  - Ein Key mit Secret-Muster, z. B. `SwagPayPal.settings.clientSecret`, wird als `***` maskiert.
  - Ein Wert `null` (Key wird gelöscht) ergibt `new = null`.
  - Ist `system_config` nicht getrackt oder das Log inaktiv, entsteht kein Eintrag.
  - Ein Aufruf im CLI-Kontext ohne Request ergibt `sourceType = 'system'`.
  - Mit Request und `sw-context`-Attribut (`PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT`) mit `AdminApiSource` ergibt `sourceType = 'user'`.
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement.**
  - Die Vorher-Werte werden roh per `Connection` gelesen: `SELECT configuration_key, configuration_value FROM system_config WHERE configuration_key IN (:keys) AND sales_channel_id <=> :sc`. Den Wert holt `json_decode(...)['_value']`. Nicht `SystemConfigService::get` verwenden, weil das vererbte Werte liefert.
  - Die Domain sind die Key-Segmente bis vor das letzte `.`.
  - Der Context kommt aus `RequestStack` → Attribut `sw-context`, sonst `Context::createDefaultContext()`.
  - **6.6.8 prüfen:** Gibt es dieselben Event-Klassen und Signaturen? Das Ergebnis kommt als Kommentar in die Klasse. Weichen sie ab, wird das ein Blocker für den User.
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: log system config changes`).

### Task 9: Cleanup-Task

**Files:**
- Create: `src/Schedules/CleanupChangeLogTask.php` (`getTaskName(): 'muwa.cleanup_change_log_task'`, `getDefaultInterval(): 86400`), `CleanupChangeLogTaskHandler.php`
- Modify: `src/Resources/config/schedule.xml`, analog zu `SendNotificationLogsTask`
- Test: `tests/ChangeLog/CleanupChangeLogTaskHandlerTest.php` (Integration)

**Interfaces:**
- Produces: `CleanupChangeLogTaskHandler::run(): void` und die öffentliche Methode `deleteOlderThan(int $days): int` (gibt die Anzahl gelöschter Zeilen zurück)

- [ ] **Step 1: Failing tests.**
  - Drei Einträge mit `created_at` vor 100, 50 und 1 Tag(en) und `retention = 90`: `run()` lässt 2 übrig.
  - Bei `retention = 0` bleiben alle.
  - Ist das Log inaktiv, bleiben alle.
  - Bei 2500 alten Einträgen ist `deleteOlderThan` nach mehreren Blöcken à 1000 Zeilen fertig, und alle sind weg.
- [ ] **Step 2: Run, expect FAIL.**
- [ ] **Step 3: Implement** mit `DELETE … WHERE created_at < :threshold LIMIT 1000` in einer Schleife, solange 1000 Zeilen betroffen sind. `:threshold` wird in PHP als UTC-`Y-m-d H:i:s.v` berechnet.
- [ ] **Step 4: Run, expect PASS.**
- [ ] **Step 5: Commit** (`feat: add change log cleanup task`).

### Task 10: Admin – Modul, Liste und Diff

**Files:**
- Create: `module/muwa-change-log/index.js`, `page/muwa-change-log-index/*`, `component/muwa-change-log-list/*`, `component/muwa-change-log-diff/*`, `snippet/de-DE.json`, `en-GB.json`
- Modify: `main.js` mit dem Import und `addPrivilegeMappingEntry`:
  - `category: 'permissions'`, `parent: 'settings'`, `key: 'muwa_change_log'`
  - `roles.viewer.privileges = ['muwa_admin_change_log:read', 'user:read', 'integration:read', 'sales_channel:read', 'language:read']`

**Interfaces:**
- Produces:
  - Komponente `muwa-change-log-list` mit den Props `entityName: String|null`, `entityId: String|null` und `compact: Boolean` (default `false`). Sind beide Props gesetzt, sind die Filter für Entität und Datensatz fest und ausgeblendet. `compact` blendet die Spalten IP und Entität aus, für die CMS-Sidebar.
  - Komponente `muwa-change-log-diff` mit dem Prop `changes: Array` (Format `FieldChange::toArray()`)
  - Route `muwa.change.log.index` mit dem Privileg `muwa_change_log.viewer`

- [ ] **Step 1: Implement the module.**
  - Der Eintrag liegt unter `settingsItem`, Gruppe `plugins`, analog zu `muwa-log-viewer`.
  - Die Liste nutzt `repositoryFactory.create('muwa_admin_change_log')` mit `Criteria` (Seite, Limit 25, `addSorting(createdAt DESC)`, `setTerm(search)`).
  - **Filter:**
    - `range('createdAt', {gte, lte})` über zwei `sw-datepicker`
    - Wer: `sw-single-select` mit den Optionen „Alle“, „System“, die Admin-User (`user`), die Integrationen und die Sales Channels. Gefiltert wird mit `equals` auf `userId`, `integrationId` bzw. `salesChannelId` oder auf `sourceType = 'system'`.
    - Entität: `sw-multi-select` über die getrackten Entitäten mit `equalsAny('entityName')`
  - **Zeile:** Datum, Aktions-Badge (`sw-label` mit den Varianten info, success und danger), Entität, `entityLabel`, Wer mit `sourceType` als Untertitel, IP und „n Felder“. Ein Klick klappt `muwa-change-log-diff` auf.
  - **Diff:** Pro Feld gibt es eine Überschrift mit Feldname und Kontext. `languageId` wird über `language`-Repository und Locale zu `(en-GB)`, `linkedEntity` zu „Zuordnung …“. Darunter stehen Zeilen mit `−` (rot) für alt und `+` (grün) für neu. Bei `masked` steht `***`. Wie Werte formatiert werden, entscheidet der Builder (Spec 7).
  - Als Referenz dient das Mockup „Variante B“: `/Users/torstenfreyda/shopdev/sw6/sw67/.superpowers/brainstorm/32364-1793723752/content/log-list.html`
- [ ] **Step 2: Build and verify in the browser.**
  - Admin-Build ausführen (siehe Global Constraints).
  - Im Admin Einstellungen › Plugins › Änderungslog öffnen.
  - Im Admin ein Produkt umbenennen (en-GB) und den Preis ändern.
  - Erwartet: 1 Zeile, der Diff zeigt „Name (en-GB)“ und den Preis.
  - Freitextsuche nach dem Produktnamen, Datumsfilter, Wer = eigener User und Entität = Produkt filtern jeweils korrekt.
  - Ein User ohne Rolle sieht den Menüpunkt nicht.
- [ ] **Step 3: Commit** (`feat: add change log admin module`).

### Task 11: Admin – Entitäten-Auswahl, Detail-Tabs, CMS-Sidebar

**Files:**
- Create: `component/muwa-change-log-entity-select/*`, `extension/sw-product-detail/*`, `extension/sw-category-detail/*`, `extension/sw-order-detail/*`, `extension/sw-customer-detail/*`, `extension/sw-cms-detail/*`
- Modify: `module/muwa-change-log/index.js` mit `routeMiddleware` für die Kind-Routen der Tabs

**Interfaces:**
- Consumes: `muwa-change-log-list` (Props aus Task 10)
- Produces:
  - Die Komponente `muwa-change-log-entity-select` (v-model auf ein Array) zeigt alle Keys aus `Shopware.EntityDefinition.getDefinitionRegistry()` ohne `*_translation`, `*_mapping` und `muwa_admin_change_log`, alphabetisch und durchsuchbar, per `sw-multi-select`. Für `system_config` lautet das Label „Einstellungen“.

- [ ] **Step 1: Implement.**
  - Jeder Tab ist ein `sw-tabs-item` „Änderungsverlauf“ mit der Kind-Route `<modul>.detail.muwa-change-log` und rendert `<muwa-change-log-list :entity-name="…" :entity-id="$route.params.id">`.
  - Sichtbar ist der Tab nur, wenn `acl.can('muwa_change_log.viewer')` gilt, `activeChangeLog` an ist und die Entität in `changeLogEntities` steht. Gelesen wird beides per `systemConfigApiService.getValues('MuckiLogPlugin.config')`.
  - In `sw-cms-detail` kommt ein `sw-sidebar-item` mit `<muwa-change-log-list compact>`.
- [ ] **Step 2: Build and verify in the browser.**
  - Die Tabs erscheinen auf Produkt, Kategorie, Bestellung und Kunde und zeigen nur die Commits des jeweiligen Datensatzes.
  - Die CMS-Sidebar zeigt den Verlauf der Seite.
  - Wird „Produkt“ in den Plugin-Einstellungen abgewählt, verschwindet der Tab.
  - Die Entitäten-Auswahl speichert und lädt korrekt.
- [ ] **Step 3: Commit** (`feat: add change log tabs and entity select`).

### Task 12: Doku, Version, Gesamtlauf

**Files:**
- Modify: `composer.json` (`"version": "v2.6.0"`), `CHANGELOG.md` (Eintrag 2.6.0), `readme.md` (Abschnitt „Admin-Änderungslog“), `CLAUDE.md`
- Die `CLAUDE.md` bekommt diese Punkte:
  - Entity `muwa_admin_change_log`
  - Config-Keys
  - Scheduled Task
  - Fallstrick: `ChangeSetAware` ist `@internal`
  - Fallstrick: `system_config` hat einen eigenen Erfassungsweg

- [ ] **Step 1: Write the docs.**
- [ ] **Step 2: Gesamte Suite auf 6.7:** `ddev mutagen sync` und dann `ddev exec "./vendor/bin/phpunit --configuration=custom/static-plugins/MuckiLogPlugin"`. Erwartet: alles grün, auch die bestehenden Tests.
- [ ] **Step 3: PHPStan:** `ddev exec "cd custom/static-plugins/MuckiLogPlugin && /var/www/html/vendor/bin/phpstan analyse -c phpstan.neon"` darf keine neuen Fehler zeigen.
- [ ] **Step 4: 6.6-CI:** Ob `main-test-sw66.yml` lokal oder in CI laufen kann, mit dem User klären. Ohne 6.6-Lauf gilt die 6.6-Kompatibilität als **nicht verifiziert** und muss so berichtet werden.
- [ ] **Step 5: Commit** (`docs: document admin change log`).
