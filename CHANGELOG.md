# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
