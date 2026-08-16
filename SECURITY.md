# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅        |

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Email **vincent@wemakeitspark.nl** with a description of the issue, the steps to
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
  fingerprint. Without `ERROR_PAGES_SALT` set, someone able to trigger errors
  could in principle confirm a guessed file path by comparing hashes. Set the
  salt in production.
- **Request IDs.** Header values are length-capped and character-validated
  before they reach the page; anything failing validation is replaced by a
  generated id rather than echoed back. A bypass that gets attacker-controlled
  text into the HTML is a vulnerability.
- **Abort messages.** Messages passed to `abort()` are shown only for the status
  codes in `messages.use_exception_message_codes`. 404 and every 5xx are
  excluded by default so framework-generated messages cannot leak internals.
- **Error pages are never cached or indexed.** They carry
  `X-Robots-Tag: noindex, nofollow`.
