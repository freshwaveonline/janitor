# Changelog

All notable changes to `janitor` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased](https://github.com/freshwaveonline/janitor/compare/v2.0.0...HEAD)

## [v2.0.0](https://github.com/freshwaveonline/janitor/compare/v1.0.0...v2.0.0) - 2026-08-17

### Changed

- **Laravel 11 is no longer supported.** The package targets Laravel 12 and 13,
  which means `illuminate/*` at `^12.0|^13.0` and Carbon 3. PHP 8.2 remains the
  floor for Laravel 12; Laravel 13 requires PHP 8.3.
- `colors.primary` now ships as `null` rather than `#4f46e5`. The rendered
  default is unchanged — the palette falls back to the same colour — but "no
  colour was chosen here" is now expressible, which is what lets an active
  Filament panel supply the accent. An application with a **published** config
  file keeps its literal `#4f46e5`, and that value now correctly wins over the
  panel; set it to `null` to inherit from Filament again.
- Explicit config wins over Filament for the primary colour and the login URL,
  matching the documented rule and the rest of the branding resolver.
- JSON error bodies are encoded with Symfony's escaping defaults
  (`JSON_HEX_TAG` and friends). Decoded values are unchanged.

### Added

- A minimal fallback page. When rendering the real page throws — a missing view,
  a broken translator, an integration that changed under the package — the
  response is a plain page built without config, translations, views or URL
  generation, carrying the status code and nothing from the exception.
- Every collaborator is guarded individually, so a failing branding resolver,
  action resolver, message-number generator, request-id resolver or retry
  resolver costs its own contribution instead of the whole page.
- Action and branding URLs are filtered by scheme: `javascript:`, `vbscript:`,
  `data:`, `file:` and `blob:` are refused for links, matching the way browsers
  normalise a URL before resolving its scheme.

### Fixed

- PHPStan aborted before analysing anything, because Larastan's `extension.neon`
  and phpstan-deprecation-rules' `rules.neon` were included on top of
  `phpstan/extension-installer`. The 54 errors underneath are fixed too.
- `request_id.response_header` set to `null` now disables the response header,
  as the config file has always said it does.
- A request-id resolver that throws no longer takes down every successful
  request through the global middleware.
- The asset-injection middleware ships the page without the Livewire handler
  when the partial cannot render, instead of turning a working page into a 500.

## [v1.0.0](https://github.com/freshwaveonline/janitor/compare/v0.0.1...v1.0.0) - 2026-08-16

First stable release. The feature set is the one described under `0.0.1` below;
this tag promotes it out of `0.0.x` and commits to semantic versioning for it.

**Full Changelog**: https://github.com/freshwaveonline/janitor/commits/v1.0.0

## [0.0.1](https://github.com/freshwaveonline/janitor/releases/tag/v0.0.1) - 2026-08-16

First release. The API may still change while the package is at `0.0.x`.

### Changed

- The package moved to the `freshwaveonline` organisation and was renamed to
  **Janitor**. Nothing was released under the old name, so there is no upgrade
  path to follow — but for anyone who tracked the branch, everything moved at
  once: package `freshwaveonline/janitor`, namespace `FreshwaveOnline\Janitor\`,
  config `config/janitor.php`, views and translations `janitor::`, environment
  variables `JANITOR_*`, command `janitor:install`, preview route `/_janitor`,
  facade `Janitor`, and the CSS prefix `jn-`.

### Added

- Informative error pages for every 4xx and 5xx status, with a message, a reason,
  an explanation and concrete suggestions per status code.
- Deterministic **message numbers**: a hash of where the error happened, so the
  same failure always produces the same quotable code across servers and deploys.
- **Request ID** resolution from `X-Request-Id`, `X-Correlation-Id`, AWS ALB,
  Google Cloud Trace, Cloudflare and W3C `traceparent` headers, with generation
  as a fallback and a middleware that assigns it at the start of every request.
- **Retry-After** support: an absolute time, a live countdown, and a retry button
  that stays disabled until the moment passes.
- **Call-to-action buttons** per status code, configurable, with built-ins for
  home, back, reload, retry, sign in, support and status page.
- **Livewire** support: render errors as a positioned pop-up (nine positions via
  a `ModalPosition` enum) or as a full page.
- Optional **Filament** integration that inherits the active panel's brand name,
  logo, primary colour, home URL and login URL. Never a hard dependency.
- Light and dark mode, driven by a single configured primary colour with optional
  per-scheme overrides and automatic WCAG contrast correction.
- English and Dutch translations, publishable and overridable.
- A copyable technical block on 5xx errors, gated per environment.
- JSON responses carrying the same message number and request ID for API clients.
- A preview route at `/_janitor` for designing against every state.
- `janitor:install` command.
- **Contracts for every moving part** — `MessageNumberGenerator`,
  `RequestIdResolver`, `RetryAfterResolver`, `BrandingResolver`,
  `ActionResolver`, `ErrorContextBuilder` and `ErrorRenderer` — each bound to a
  default implementation and replaceable with a single container binding. The
  concrete classes stay bound under their own names so the default can be
  decorated rather than replaced.
- `BrandingResolver` and the `Branding` value object, so a multi-tenant
  application can supply name, logo, colour, URLs and support address from its
  own tenant record in one method.
- Runtime icon registry: `Icons::register()`, `Icons::registerMany()` and
  `Icons::useForStatus()` add brand glyphs or replace bundled Heroicons.
- Publishable views at three override levels: the whole page, a single partial,
  or one status code via `errors/{code}.blade.php`.
