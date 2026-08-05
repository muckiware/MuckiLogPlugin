# Shopware 6.7 Kompatibilitaetsanalyse - MuckiLogPlugin

## Wichtiger Hinweis
Dieses Plugin muss sowohl fuer Shopware 6.6 als auch fuer 6.7 funktionieren.
Feature-Flags duerfen NICHT entfernt werden, da sie in 6.6 noch aktiv sind.

## Zusammenfassung
Das Plugin ist weitgehend kompatibel mit Shopware 6.7.
Es hat keine Admin-UI (kein Vue/Webpack/Vite), was die Migration vereinfacht.

## Durchgefuehrte Anpassungen (6.6 + 6.7 kompatibel)

### 1. ErrorControllerDecorator.php
- Removed: use Faker\Core\Uuid (unnoetiger Import, wurde nirgends verwendet)
- BEHALTEN: cache_rework Feature-Branch (fuer 6.6 noetig, in 6.7 deprecated aber harmlos)
- BEHALTEN: ACCESSIBILITY_TWEAKS Feature-Flag if/else (fuer 6.6 noetig)
- BEHALTEN: Deprecated captcha input rendering else-Zweig (fuer 6.6 noetig)
- BEHALTEN: Feature import (wird noch fuer 6.6 Feature-Checks benoetigt)

### 2. Services/LoggerServiceDecorator.php
- Removed: use http\Message (unnoetiger Import, wurde nirgends verwendet)

## Keine Anpassung noetig (6.6 und 6.7 kompatibel)

### Commands - $defaultName
- public static $defaultName ist in SW 6.7 deprecated aber NICHT entfernt
- Funktioniert in beiden Versionen, keine Aenderung noetig
- Bei einem zukuenftigen 6.7-only Release kann auf setName() umgestellt werden

### composer.json - PHP Version
- PHP >= 8.1 beibehalten (SW 6.6 unterstützt PHP 8.1, SW 6.7 empfiehlt 8.2+)
- Keine Aenderung noetig fuer Dual-Compatibility

### Admin/UI
- Keine Administration-UI vorhanden -> kein Vite/Vue/Pinia Migration noetig

### Scheduled Tasks, Entity Definition, Services, Migrations, Event Subscriber
- Alle verwenden Standard-Patterns die in 6.6 und 6.7 funktionieren

## Feature-Flags die BEHALTEN werden muessen
- cache_rework: In 6.6 inaktiv (Branch wird ausgefuehrt), in 6.7 aktiv (Branch wird uebersprungen)
- ACCESSIBILITY_TWEAKS: In 6.6 inaktiv (else-Zweig), in 6.7 aktiv (if-Zweig)

## Potenzielle Risiken (nicht blockierend)
1. DBAL 4.x in SW 6.7: executeStatement() ist kompatibel (wurde bereits in 6.6 eingefuehrt)
2. PHPUnit 11.x in SW 6.7: Tests koennen Anpassungen benoetigen (CI-Pipeline)
3. Message Queue Size Limit 256KB in 6.7: Messages sind klein genug
4. Store-API Route Caching removed in 6.7: Plugin nutzt keine Cached Route Klassen

## Empfehlung fuer spaetere 6.7-only Version
Wenn das Plugin irgendwann nur noch 6.7 unterstützen muss:
- Entferne cache_rework Feature-Branch
- Vereinfache ACCESSIBILITY_TWEAKS if/else zu direktem Code
- Entferne deprecated captcha input rendering
- Stelle $defaultName auf setName() in configure() um
- Erhoehe PHP requirement auf >= 8.2
