# Contributing

Thanks for taking the time. Bug reports, translations and pull requests are all
welcome.

## Getting set up

```bash
git clone https://github.com/freshwaveonline/janitor
cd janitor
composer install
```

```bash
composer test       # Pest
composer analyse    # PHPStan / Larastan
composer format     # Laravel Pint
```

CI runs the suite against PHP 8.2–8.4 and Laravel 11–12, on both the lowest and
the highest resolvable dependencies. Run `composer test` and `composer format`
before opening a pull request and the matrix will usually agree with you.

## Seeing your change

Every error state renders at `/_janitor` in a local app, so you rarely need
to provoke a real error:

```
/_janitor                     all states
/_janitor/500?details=1       with the technical block
/_janitor/429?retry=120       with a countdown
/_janitor/503?theme=dark      dark surface
/_janitor/500?modal=1         the Livewire pop-up
```

## What a good pull request looks like

- **One change.** Separate pull requests are easier to review and to revert.
- **A test that fails without it.** The suite is the specification; a change
  with no test is a change nobody can safely refactor around later.
- **The existing style.** Pint enforces the formatting; match the surrounding
  naming and comment density for the rest.

## Adding a translation

Copy `resources/lang/en` to `resources/lang/<locale>` and translate
`errors.php` and `ui.php`. You only need to translate what differs from the
fallbacks — lookup walks status → family (`4xx`/`5xx`) → `default`, so a locale
that only defines `default`, `4xx` and `5xx` already covers every status code.

Placeholders `:status`, `:brand`, `:message_number` and `:support_email` must
survive translation.

## Things to keep in mind

Two constraints are easy to break by accident, and both matter more than they
look:

- **No external requests.** All CSS, JavaScript and icons are inlined on
  purpose. An error page that pulls from a CDN or the asset pipeline fails
  exactly when it is needed. Please do not add a `<link>` or a `<script src>`.
- **Message numbers must stay stable.** The number is a hash of a normalised
  `file:line` fingerprint. Anything that makes the same failure hash differently
  across servers or deploys — an absolute path, a timestamp, a user id —
  silently breaks the feature for everyone who has already quoted a number.

Livewire and Filament are optional dependencies. Neither may become required,
and every call into them stays behind `class_exists()` and a `try`/`catch`.

## Security

Please do not open a public issue for a security problem — see
[SECURITY.md](SECURITY.md).
