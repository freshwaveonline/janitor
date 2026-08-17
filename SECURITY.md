# Security Policy

## Supported versions

| Version | Supported | Laravel  |
|---------|-----------|----------|
| 2.x     | ✅        | 12, 13   |
| 1.x     | ❌        | 11, 12   |

Janitor 2.x supports Laravel 12 and 13 on PHP 8.2+ (Laravel 13 requires PHP
8.3+). 1.x is no longer maintained; an application still on Laravel 11 should
upgrade the framework rather than stay on 1.x for security fixes.

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Email **mail@freshwave.online** with a description of the issue, the steps to
reproduce it, and the affected version. You will get an acknowledgement within a
few working days, and a fix or an explanation of why it is not one.

## What this package touches

Some notes on the parts most worth scrutinising, so a report can get to the point:

- **Exception details.** The technical block on a 5xx is gated by
  `details.visibility` and, on `Auto`, by `app.debug` or the environment
  allow-list. It renders the exception class, message, file, line and a stack
  trace — never request bodies, headers, session data, environment variables or
  bound method arguments. A route by which any of those reach the page is a
  vulnerability.
- **Message numbers.** These are a hash of a project-relative `file:line`
  fingerprint. Without `JANITOR_SALT` set, someone able to trigger errors
  could in principle confirm a guessed file path by comparing hashes. Set the
  salt in production.
- **Request IDs.** Header values are length-capped and character-validated
  before they reach the page; anything failing validation is replaced by a
  generated id rather than echoed back. A bypass that gets attacker-controlled
  text into the HTML is a vulnerability.
- **Abort messages.** Messages passed to `abort()` are shown only for the status
  codes in `messages.use_exception_message_codes`. 404 and every 5xx are
  excluded by default so framework-generated messages cannot leak internals.
- **Link targets.** Every URL that reaches an `href` or an `src` — from config,
  from a custom `BrandingResolver`, or from the active Filament panel — is
  filtered by scheme. Escaping makes a URL safe as markup and does nothing about
  what it does when followed, so `javascript:`, `vbscript:`, `data:`, `file:`
  and `blob:` are refused for links. A URL that executes on click is a
  vulnerability.
- **Error pages are never cached or indexed.** They carry
  `X-Robots-Tag: noindex, nofollow`.
- **The fallback page.** When rendering fails, the response is built without
  config, translations or views, and carries the status code and a fixed
  sentence. It must never carry anything from the exception, precisely because
  the checks that decide what may be shown are what failed.
