# Changelog

All notable changes to `laravel-error-pages` are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
- A preview route at `/_error-pages` for designing against every state.
- `error-pages:install` command.

[Unreleased]: https://github.com/vvdboogaard/laravel-error-pages/commits/main
