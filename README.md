# Laravel Error Pages

[![tests](https://github.com/vvdboogaard/laravel-error-pages/actions/workflows/tests.yml/badge.svg)](https://github.com/vvdboogaard/laravel-error-pages/actions/workflows/tests.yml)
[![static analysis](https://github.com/vvdboogaard/laravel-error-pages/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/vvdboogaard/laravel-error-pages/actions/workflows/static-analysis.yml)
[![latest version](https://img.shields.io/packagist/v/vvdboogaard/laravel-error-pages.svg)](https://packagist.org/packages/vvdboogaard/laravel-error-pages)
[![license](https://img.shields.io/packagist/l/vvdboogaard/laravel-error-pages.svg)](LICENSE.md)

Laravel's default error pages show a status code and one line of text. That is
enough for a developer and almost useless for the person who hit the error.

This package replaces them with pages that actually explain the situation: what
happened, why, what to do next, a button that does it, and — when something went
wrong on your side — a short code the visitor can quote so you can find that
exact error in your logs.

```
┌──────────────────────────────────────────────────┐
│                     Acme                         │
│  ┌────┐                                          │
│  │ 🔍 │  404                                      │
│  └────┘  We could not find this page             │
│          The page you are looking for does not   │
│          exist, or is no longer available.       │
│                                                  │
│  ⓘ WHAT HAPPENED                                 │
│    The address is unknown to us. It may have     │
│    been moved, renamed or removed.               │
│                                                  │
│  WHAT YOU CAN DO                                 │
│   ✓ Check the address for typos.                 │
│   ✓ Go back to the previous page.                │
│   ✓ Start over from the home page.               │
│                                                  │
│  [ ← Go back ]  [ ⌂ Go to home page ]            │
│  ──────────────────────────────────────────────  │
│  # Message number ERR-3F9A2C   ⧉ Request ID …    │
└──────────────────────────────────────────────────┘
```

## Why

- **The visitor learns something.** Every status code has a message, a reason, an
  explanation and a list of things to try — in their language, not HTTP's.
- **Support gets a handle.** The message number is a hash of *where* the error
  happened, so the same bug always produces the same code. Two tickets quoting
  `ERR-3F9A2C` are the same bug. It is written to your logs too.
- **It works when everything else is broken.** All CSS, JavaScript and icons are
  inlined. No CDN, no Vite build, no external request — an error page that
  depends on the asset pipeline fails exactly when you need it.
- **Nothing is required.** Livewire and Filament are used when present and
  ignored when not.

## Installation

```bash
composer require vvdboogaard/laravel-error-pages
```

That is the whole setup — the package registers itself and starts rendering. To
tune it, publish the config:

```bash
php artisan error-pages:install
```

or publish assets individually:

```bash
php artisan vendor:publish --tag=error-pages-config
php artisan vendor:publish --tag=error-pages-views
php artisan vendor:publish --tag=error-pages-lang
```

**Requirements:** PHP 8.2+, Laravel 11 or 12.

## Quick start

Four environment variables cover most projects:

```dotenv
ERROR_PAGES_PRIMARY="#4f46e5"
ERROR_PAGES_SUPPORT_EMAIL="support@acme.test"
ERROR_PAGES_HOME_URL="https://acme.test"
ERROR_PAGES_PREFIX="ACME"
```

Then look at every page without breaking anything:

```
php artisan serve
open http://localhost:8000/_error-pages
```

The preview lists all eighteen states and accepts `?theme=dark`, `?details=1`,
`?retry=120`, `?modal=1` and `?position=top-right`.

## What you get

### Message numbers

```
Message number   ERR-3F9A2C
```

The number is derived from a normalised `app/Http/Controllers/OrderController.php:88`
fingerprint — never from the message, the timestamp or the user. That means:

- The same failure gives the same number on every server and after every deploy
  (absolute paths and release directories are stripped first).
- A different line gives a different number.
- It appears on the page, in the `X-Message-Number` response header, and in your
  log context, so `grep ERR-3F9A2C storage/logs` finds the exception.

```php
'message_number' => [
    'prefix' => env('ERROR_PAGES_PREFIX', 'ERR'),  // ERR-3F9A2C
    'length' => 6,
    'alphabet' => MessageNumberAlphabet::Hex,      // Hex | Numeric | Base36
    'origin' => OriginStrategy::Application,       // Application | Thrown | RootCause
    'salt' => env('ERROR_PAGES_SALT'),
],
```

`OriginStrategy::Application` is the default and usually the right one: a
`QueryException` thrown deep inside Illuminate still fingerprints to the line in
*your* code that caused it.

> Set `ERROR_PAGES_SALT` in production. Without it, someone who can trigger errors
> could confirm a guessed file path by comparing hashes. Keep the salt stable —
> changing it changes every number you have ever quoted.

### Request IDs

Read from `X-Request-Id`, `X-Correlation-Id`, `X-Amzn-Trace-Id`,
`X-Cloud-Trace-Context`, `CF-Ray` or W3C `traceparent` — AWS, Google Cloud and
traceparent formats are unwrapped to the bare trace id. When nothing upstream
supplied one, the package generates it at the *start* of the request, so the id
on the page matches the id in every log line for that request.

Header values are length-capped and character-validated before they reach the
page; anything unusual is replaced by a generated id rather than echoed back.

### Retry-After

For 429s, maintenance mode and any exception carrying a `Retry-After` or
`X-RateLimit-Reset` header, the page shows *when* to come back:

> **When to try again** — You can try again at 14:32. *(04:12)*

The countdown ticks live and the retry button is disabled until it reaches zero.
Waits longer than `retry_after.max_seconds` (a day by default) show no countdown
at all, because "try again in three days" is not something a visitor can act on.

### Call-to-action buttons

Configured per status code, from built-ins (`home`, `back`, `reload`, `retry`,
`login`, `support`, `status_page`) or inline definitions:

```php
'actions' => [
    401 => ['login', 'home'],
    404 => ['back', 'home', [
        'label' => 'Browse the catalogue',
        'url' => '/products',
        'icon' => 'magnifying-glass',
        'style' => 'ghost',
    ]],
    429 => ['retry', 'home'],
    'default' => ['back', 'home'],
],
```

An action that cannot resolve removes itself: no support address configured means
no support button, no `login` route means no sign-in button. Exactly one button
always carries the primary emphasis.

### The 500 page

On a 500 the page can show the exception, formatted and copyable:

```php
'details' => [
    'visibility' => DetailVisibility::Auto,   // Auto | Always | Never
    'environments' => ['local', 'development', 'testing', 'staging'],
    'replace_debug_page' => false,
],
```

`Auto` shows it when `app.debug` is on **or** when the current environment is in
the list. That is the useful case: on staging (`debug=false`, `env=staging`)
testers get a full stack trace and a **Copy report** button that puts the message
number, request id, URL, exception and trace on the clipboard in one click — while
production shows nothing.

`replace_debug_page` is off by default, so locally you keep Ignition. Turn it on
to design against these pages.

The copied report contains only what is deliberately put in it — never request
bodies, headers, session data or environment variables.

### Livewire

An error during a Livewire round-trip normally drops Livewire's iframe overlay
over your page. Instead:

```php
'livewire' => [
    'mode' => LivewireErrorMode::Modal,        // Modal | Page | Disabled
    'position' => ModalPosition::BottomRight,
    'inject_assets' => true,
    'dismissible' => true,
    'auto_dismiss' => 0,
],
```

`Modal` renders the same information as a pop-up and leaves the page — and
whatever the user had typed — completely intact. `ModalPosition` has nine values:
`TopLeft`, `TopCenter`, `TopRight`, `MiddleLeft`, `Center`, `MiddleRight`,
`BottomLeft`, `BottomCenter`, `BottomRight`. `Center` gets a backdrop; the rest
behave as toasts.

The handler is injected automatically when Livewire is installed. To place it
yourself, set `inject_assets` to `false` and add `@errorPagesScripts` to your
layout.

### Filament

If Filament is installed, an error inside a panel inherits the panel's brand
name, logo, primary colour, home URL and login URL:

```php
'filament' => [
    'enabled' => true,
    'only_on_panel_routes' => true,
    'inherit' => [
        'brand_name' => true,
        'brand_logo' => true,
        'primary_color' => true,
        'home_url' => true,
        'login_url' => true,
    ],
],
```

Everything you set explicitly in this package's config wins over Filament. Every
call into Filament is guarded, so a Filament upgrade that renames a method turns
into "no branding inherited", never into a 500 on your error page.

### Colours, light and dark

You configure one colour. The package derives the hover state, the active state,
the soft background, the border, the focus ring and the readable text colour on
top of it, for both schemes:

```php
'colors' => [
    'primary' => env('ERROR_PAGES_PRIMARY', '#4f46e5'),
    'light' => env('ERROR_PAGES_PRIMARY_LIGHT'),   // optional override
    'dark' => env('ERROR_PAGES_PRIMARY_DARK'),     // optional override
    'auto_contrast' => true,
],

'theme' => Theme::Auto,   // Auto | Light | Dark
```

`light` and `dark` exist for brand colours that work on white and vanish on
near-black. When they are empty, `primary` is used for both.

With `auto_contrast` on, a primary colour that fails WCAG contrast against its own
surface is nudged lighter or darker until it passes — a pale yellow brand colour
still produces a readable button instead of an invisible one.

Everything else is neutral grey. That is deliberate: the pages stay white-label
until you give them a colour.

### Translations

English and Dutch ship with the package. Copy resolution falls back from the
status code to the family to the default, so you only translate what differs:

```
error-pages::errors.404.title  →  error-pages::errors.4xx.title  →  error-pages::errors.default.title
```

Each status has `title`, `message`, `reason`, `explanation` and `suggestions`,
with `:status`, `:brand`, `:message_number` and `:support_email` placeholders.

```bash
php artisan vendor:publish --tag=error-pages-lang
```

### API responses

Requests that expect JSON get the same information in a machine-readable shape:

```json
{
    "message": "This is not your fault — something failed while we were handling your request.",
    "title": "Something went wrong on our side",
    "status": 500,
    "message_number": "ERR-3F9A2C",
    "request_id": "0f8fad5b-d9cb-469f-a165-70867728950e"
}
```

## Customising

### Your own view for one status code

Publish the views and add `resources/views/vendor/error-pages/errors/404.blade.php`.
It is picked up automatically and receives the same `$error` context.

### The error inside your own layout

```php
'views' => ['layout' => 'layouts.app'],
```

The card is then rendered into your layout's `content` section instead of as a
standalone document.

### Keeping Laravel's behaviour

`resources/views/errors/404.blade.php` in your application wins — the package
steps aside for any error view you wrote yourself. Turn that off with
`views.prefer_application_views`.

To disable the package for specific codes or exceptions:

```php
'except_codes' => [422],
'except_exceptions' => [
    Illuminate\Validation\ValidationException::class,
    Illuminate\Http\Exceptions\HttpResponseException::class,
    Illuminate\Auth\AuthenticationException::class,
],
```

Validation and authentication exceptions are excluded by default because Laravel
redirects for them — hijacking those would break every form and every login
redirect in your application. The one exception: when your app has no `login`
route, `handle_missing_login_route` renders a proper 401 page instead of letting
Laravel throw a `RouteNotFoundException` and turn it into a 500.

### Abort messages

```php
abort(403, 'You may not edit a published post.');
```

That message is shown to the visitor, because you wrote it. Framework-generated
messages are not: 404 and every 5xx are excluded by default, so a
`ModelNotFoundException` cannot leak your model class names onto the page.

```php
'messages' => [
    'use_exception_message' => true,
    'use_exception_message_codes' => [400, 402, 403, 409, 410, 423, 429],
],
```

## Testing

```bash
composer test       # Pest
composer analyse    # PHPStan / Larastan
composer format     # Laravel Pint
```

If PHPStan reports pre-existing findings you would rather fix over time:

```bash
vendor/bin/phpstan analyse --generate-baseline
```

then uncomment the `baseline` line in `phpstan.neon.dist`.

## Contributing

Pull requests are welcome. Please run `composer test` and `composer format`
before opening one — CI runs the suite against PHP 8.2–8.4 and Laravel 11–12, on
both the lowest and the highest resolvable dependencies.

## Security

If you discover a security issue, email vincent@wemakeitspark.nl rather than
opening a public issue.

## Credits

- [Vincent van den Boogaard](https://github.com/vvdboogaard)
- Icons from [Heroicons](https://heroicons.com) (MIT), inlined.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
