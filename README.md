# Janitor

**Informative, white-label error pages for Laravel.**

[![tests](https://github.com/freshwaveonline/janitor/actions/workflows/tests.yml/badge.svg)](https://github.com/freshwaveonline/janitor/actions/workflows/tests.yml)
[![static analysis](https://github.com/freshwaveonline/janitor/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/freshwaveonline/janitor/actions/workflows/static-analysis.yml)
[![latest version](https://img.shields.io/packagist/v/freshwaveonline/janitor.svg)](https://packagist.org/packages/freshwaveonline/janitor)
[![license](https://img.shields.io/packagist/l/freshwaveonline/janitor.svg)](LICENSE.md)

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

**Requirements:** PHP 8.2+, Laravel 11 or 12.

```bash
composer require freshwaveonline/janitor
```

The package registers itself through Laravel's auto-discovery and starts
rendering immediately.

<details>
<summary><strong>Installing from a private fork or a mirror</strong></summary>

If you run this from a repository of your own rather than from Packagist, point
Composer at it directly. In the **consuming application's** `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/freshwaveonline/janitor"
        }
    ]
}
```

```bash
composer require freshwaveonline/janitor:^1.0
```

Composer needs credentials for the private repository. Either a GitHub token
with `repo` scope:

```bash
composer config --global github-oauth.github.com ghp_yourtokenhere
```

or SSH, by using `git@github.com:freshwaveonline/janitor.git` as the
`url` and relying on your existing SSH key. On a deploy server, use a machine
user or a deploy key rather than a personal token.

Before a tag exists, require the branch instead — `dev-main` is aliased to
`1.0.x-dev`, so `^1.0` already resolves against it:

```bash
composer require freshwaveonline/janitor:dev-main
```

</details>

### For local development

To work on the package and the application side by side, use a path repository.
Composer symlinks the directory, so edits are picked up without reinstalling:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../janitor",
            "options": { "symlink": true }
        }
    ]
}
```

```bash
composer require freshwaveonline/janitor:@dev
```

### Releasing a version

Composer resolves versions from git tags, so cutting a release is a tag:

```bash
git tag v1.0.0
git push origin v1.0.0
```

That runs the `release` workflow, which validates `composer.json`, runs the
suite and publishes a GitHub release with generated notes. The Packagist GitHub
App picks the tag up from there.

## Configuring

To tune the package, publish the config:

```bash
php artisan janitor:install
```

or publish assets individually:

```bash
php artisan vendor:publish --tag=janitor-config
php artisan vendor:publish --tag=janitor-views
php artisan vendor:publish --tag=janitor-lang
```

## Quick start

Four environment variables cover most projects:

```dotenv
JANITOR_PRIMARY="#4f46e5"
JANITOR_SUPPORT_EMAIL="support@acme.test"
JANITOR_HOME_URL="https://acme.test"
JANITOR_PREFIX="ACME"
```

Then look at every page without breaking anything:

```
php artisan serve
open http://localhost:8000/_janitor
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
    'prefix' => env('JANITOR_PREFIX', 'ERR'),  // ERR-3F9A2C
    'length' => 6,
    'alphabet' => MessageNumberAlphabet::Hex,      // Hex | Numeric | Base36
    'origin' => OriginStrategy::Application,       // Application | Thrown | RootCause
    'salt' => env('JANITOR_SALT'),
],
```

`OriginStrategy::Application` is the default and usually the right one: a
`QueryException` thrown deep inside Illuminate still fingerprints to the line in
*your* code that caused it.

> Set `JANITOR_SALT` in production. Without it, someone who can trigger errors
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
yourself, set `inject_assets` to `false` and add `@janitorScripts` to your
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
    'primary' => env('JANITOR_PRIMARY', '#4f46e5'),
    'light' => env('JANITOR_PRIMARY_LIGHT'),   // optional override
    'dark' => env('JANITOR_PRIMARY_DARK'),     // optional override
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
janitor::errors.404.title  →  janitor::errors.4xx.title  →  janitor::errors.default.title
```

Each status has `title`, `message`, `reason`, `explanation` and `suggestions`,
with `:status`, `:brand`, `:message_number` and `:support_email` placeholders.

```bash
php artisan vendor:publish --tag=janitor-lang
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

## Customising the views

```bash
php artisan vendor:publish --tag=janitor-views
```

Every blade file lands in `resources/views/vendor/janitor/`. Laravel's
namespaced view finder checks that directory first, so a published file wins
immediately — no config, no registration. Delete a file to fall back to the
packaged version.

```
resources/views/vendor/janitor/
├── error.blade.php               ← the standalone page
├── embedded.blade.php            ← the card inside your own layout
├── preview.blade.php
├── preview-modal.blade.php
└── partials/
    ├── card.blade.php            ← header, blocks, actions, meta, details
    ├── styles.blade.php          ← the entire inline stylesheet
    ├── brand.blade.php
    ├── brand-mark.blade.php
    ├── actions.blade.php         ← the CTA buttons
    ├── meta.blade.php            ← message number / request ID chips
    ├── retry.blade.php           ← the countdown block
    ├── details.blade.php         ← the 5xx exception block
    ├── scripts.blade.php         ← copy buttons + countdown
    └── livewire-script.blade.php ← the pop-up handler
```

There are three levels of override, and you pick the smallest one that does the
job:

**One partial.** Change the meta chips and keep everything else:

```blade
{{-- resources/views/vendor/janitor/partials/meta.blade.php --}}
<div class="my-meta">{{ $error->messageNumber }}</div>
```

**The whole page.** Replace `error.blade.php` and lay the page out yourself:

```blade
<!DOCTYPE html>
<html>
<body>
    <h1>{{ $error->statusCode }} — {{ $error->title }}</h1>
    <p>{{ $error->message }}</p>

    @foreach ($error->actions() as $action)
        <a href="{{ $action->url }}">{{ $action->label }}</a>
    @endforeach
</body>
</html>
```

**One status code.** Add `errors/{code}.blade.php` under the published
directory and only that status gets the bespoke page:

```blade
{{-- resources/views/vendor/janitor/errors/404.blade.php --}}
<h1>Bespoke 404 — {{ $error->title }}</h1>
```

Every view receives the same `$error` object
(`FreshwaveOnline\Janitor\Data\ErrorContext`):

| Property | What it holds |
|---|---|
| `$error->statusCode` | `404` |
| `$error->title` / `->message` | translated headline and lead |
| `$error->reason` / `->explanation` | the "what happened" block |
| `$error->suggestions` | `list<string>` |
| `$error->actions()` | `list<ErrorAction>` — `label`, `url`, `icon`, `style`, `behaviour` |
| `$error->messageNumber` / `->requestId` | the quotable codes |
| `$error->retryAt` / `->retryInSeconds()` | the retry moment |
| `$error->details` | `ExceptionDetails` or null |
| `$error->branding` | `Branding` — `name`, `logo`, `homeUrl`, `supportEmail`, … |
| `$error->palette` | resolved colour tokens for both schemes |
| `$error->copyReport()` | the plain-text support report |

### The error inside your own layout

```php
'views' => ['layout' => 'layouts.app'],
```

The card is then rendered into your layout's `content` section instead of as a
standalone document.

## Extending

Every moving part is bound by contract, so you replace one piece from your own
`AppServiceProvider` and the rest of the package keeps working around it.

| Contract | Default | Replace it when |
|---|---|---|
| `MessageNumberGenerator` | `MessageNumber` | you already have an incident-code scheme |
| `RequestIdResolver` | `RequestId` | your APM assigns the id |
| `RetryAfterResolver` | `RetryAfter` | the moment comes from a deploy window or queue depth |
| `BrandingResolver` | `ConfigBranding` | branding is per tenant, not per app |
| `ActionResolver` | `ActionFactory` | the buttons depend on runtime state |
| `ErrorContextBuilder` | `ErrorContextFactory` | the copy lives in a CMS, not in lang files |
| `ErrorRenderer` | `ErrorPageRenderer` | you want different take-over rules |

The multi-tenant case, which is the one that comes up most:

```php
use FreshwaveOnline\Janitor\Contracts\BrandingResolver;
use FreshwaveOnline\Janitor\Data\Branding;

class TenantBranding implements BrandingResolver
{
    public function resolve(Request $request, int $statusCode): Branding
    {
        $tenant = Tenant::forHost($request->getHost());

        return new Branding(
            name: $tenant?->name,
            logo: $tenant?->logo_url,
            primaryColor: $tenant?->brand_colour,
            homeUrl: $tenant?->url,
            supportEmail: $statusCode >= 500 ? $tenant?->support_email : null,
        );
    }
}
```

```php
// AppServiceProvider::register()
$this->app->bind(BrandingResolver::class, TenantBranding::class);
```

The concrete classes stay bound under their own names, so you can decorate the
default instead of replacing it wholesale:

```php
$this->app->bind(BrandingResolver::class, fn ($app) => new class ($app->make(ConfigBranding::class)) implements BrandingResolver {
    public function __construct(private ConfigBranding $inner) {}

    public function resolve(Request $request, int $status): Branding
    {
        return $this->inner->resolve($request, $status)->with(primaryColor: tenant()->colour);
    }
});
```

`ErrorContextFactory` and `ErrorPageRenderer` are non-final with protected
methods throughout, so subclassing one method is often enough:

```php
class CopyFromCms extends ErrorContextFactory
{
    protected function optionalLine(int $status, string $key, ?string $number, Branding $branding): ?string
    {
        return Cms::line($status, $key) ?? parent::optionalLine($status, $key, $number, $branding);
    }
}
```

### Custom icons

Icons are SVG path data, so they are registered rather than configured:

```php
// AppServiceProvider::boot()
Icons::register('acme-mark', 'M12 2 2 22h20L12 2z');
Icons::useForStatus(404, 'acme-mark');
```

Registering an existing name replaces that bundled Heroicon everywhere. Any
registered name can then be used from the `actions` config.

## Keeping Laravel's behaviour

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

If you discover a security issue, email mail@freshwave.online rather than
opening a public issue.

## Credits

- [Vincent van den Boogaard](https://github.com/freshwaveonline)
- Icons from [Heroicons](https://heroicons.com) (MIT), inlined.

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
