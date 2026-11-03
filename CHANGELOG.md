# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- Fatal error on Shopware 6.6.x and early 6.7.x: `StoreClientLoggingMiddleware` was incompatible with the
  older signature of Shopware's internal Store `MiddlewareInterface`. A compiler pass now registers the
  matching middleware for the installed Shopware version; on older versions the new
  `LegacyStoreClientLoggingMiddleware` logs Store-/FRW-API responses (requests are not visible there).

## [2.6.0] - 2026-11-03

### Added

- Admin change log (opt-in): records who changed what and when, as one "commit" per root record and save
  with a before/after diff per field. Sources: admin user, integration, sales channel and system.
  Sub-entities (translations, owned child rows, mapping assignments) appear in the diff of the topmost
  tracked root record; variants get their own commits. Settings (`system_config`) are captured via a
  separate path.
- New entity `muwa_admin_change_log` and administration module "Change log" (Settings > Plugins) with
  search, filters and an expandable diff, plus a change history tab on the detail pages.
- New plugin configuration card "Change log": `activeChangeLog` (default off), `changeLogEntities`
  (default product, category, order, customer, cms_page) and `changeLogRetentionDays` (default 90,
  `0` = never delete).
- New ACL privilege `muwa_change_log.viewer`; the log is read-only in the Administration.
- New scheduled task `muwa.cleanup_change_log_task` (daily) deletes entries older than the retention.

### Known limitations

- Secrets (passwords, keys, tokens, also nested keys) are never stored; they are shown as `***` on both sides.
- Indexer-maintained or write-protected columns (e.g. order `amountTotal`, product `cheapestPrice`) are not
  logged, only their source fields.
- Writes via direct SQL are not captured (except `system_config`).
- `system_config` entries are written from the before-event, so a failing save could still be logged.
- Draft-version writes are skipped; only the merged live change is logged.
- With the Shopware feature flag `v6.8.0.0` active the detail tabs (`sw-tabs`) are not shown.
- Shopware 6.6 compatibility is not verified by a test run.

## [2.5.0] - 2026-09-29

### Added

- Outbound HTTP request logging (opt-in): logs every outgoing HTTP request made through Shopware's
  `http_client` (Store update checks, app downloader, SSO token service, S3 filesystem adapter, PSR-18
  clients) and the Store-/FRW-API (Guzzle middleware) — including URL, method, query parameters,
  request body, and response status/headers/body.
- New plugin configuration switch `activeHttpLogging` ("Active outbound HTTP request logging"),
  disabled by default.
- All outbound HTTP log entries are written to a shared `var/log/outbound-http.muwa.log` file and are
  automatically browsable through the existing Log Viewer in the Administration.

### Known limitations

- App-System-/Webhook-Guzzle-Clients (`shopware.app_system.guzzle`, `shopware.webhook.guzzle`) are not
  covered — some of their consumers require the concrete `GuzzleHttp\Client` class, which a decorator
  cannot safely satisfy.
- Third-party plugins that call `curl_*` directly instead of going through Guzzle or the Symfony HTTP
  client bypass the service container entirely and cannot be intercepted.
- No automatic masking of sensitive data (API keys, tokens, passwords in headers/body) — only enable
  for targeted debugging.

## [2.4.0] - 2026-08-20

### Added

- Log Viewer in the Administration: browse, search and download log files directly from the admin,
  without shell access (file selection, tail view with "load more", refresh, in-file search, download).

### Fixed

- Log download failing with a 500 error caused by a non-serializable HTTP cache entry.
- Incorrect match count displayed in the Log Viewer's in-file search.
