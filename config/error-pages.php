<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;
use Vvdboogaard\ErrorPages\Enums\DetailVisibility;
use Vvdboogaard\ErrorPages\Enums\LivewireErrorMode;
use Vvdboogaard\ErrorPages\Enums\MessageNumberAlphabet;
use Vvdboogaard\ErrorPages\Enums\ModalPosition;
use Vvdboogaard\ErrorPages\Enums\OriginStrategy;
use Vvdboogaard\ErrorPages\Enums\Theme;

return [

    /*
    |--------------------------------------------------------------------------
    | Master switch
    |--------------------------------------------------------------------------
    |
    | Turn the package off entirely without uninstalling it. Laravel then falls
    | back to its own error views.
    |
    */

    'enabled' => env('ERROR_PAGES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Status codes
    |--------------------------------------------------------------------------
    |
    | Which HTTP status codes this package renders. Anything not listed here is
    | handed back to Laravel untouched. Set to `['*']` to handle every 4xx/5xx.
    |
    */

    'codes' => ['*'],

    /*
    | Status codes to explicitly never handle, even when 'codes' is ['*'].
    | 422 is excluded by default because validation errors belong in the form,
    | not on a full-screen error page.
    */

    'except_codes' => [422],

    /*
    | Exception classes that must keep Laravel's own behaviour. Authentication
    | and validation exceptions redirect (to the login page / back to the form),
    | and hijacking them would break those flows.
    */

    'except_exceptions' => [
        ValidationException::class,
        HttpResponseException::class,
        AuthenticationException::class,
    ],

    /*
    | When no `login` route exists, Laravel's AuthenticationException handler
    | throws a RouteNotFoundException and the visitor gets a 500 instead of a
    | 401. Enable this to render a proper 401 page in that situation.
    */

    'handle_missing_login_route' => true,

    /*
    |--------------------------------------------------------------------------
    | Branding
    |--------------------------------------------------------------------------
    |
    | White-label by default: only the app name is shown. Point `logo` at a
    | publicly reachable URL (or an inline data URI) to replace the wordmark.
    |
    */

    'brand' => [
        'name' => env('ERROR_PAGES_BRAND', null), // null → config('app.name')
        'logo' => env('ERROR_PAGES_LOGO', null),
        'logo_dark' => env('ERROR_PAGES_LOGO_DARK', null), // optional dark-mode variant
        'logo_height' => 32,
        'show_name_beside_logo' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Colours
    |--------------------------------------------------------------------------
    |
    | `primary` drives buttons, links and accents. The greys are fixed and
    | neutral, which is what keeps the pages white-label.
    |
    | `light` and `dark` are optional overrides: when filled they replace
    | `primary` for that colour scheme. Useful when a brand colour that works
    | on white disappears on near-black.
    |
    | With `auto_contrast` on, a primary colour that fails WCAG contrast against
    | its own surface is nudged lighter/darker until it passes. Turn it off if
    | you need the exact hex you configured, contrast be damned.
    |
    */

    'colors' => [
        'primary' => env('ERROR_PAGES_PRIMARY', '#4f46e5'),
        'light' => env('ERROR_PAGES_PRIMARY_LIGHT', null),
        'dark' => env('ERROR_PAGES_PRIMARY_DARK', null),
        'auto_contrast' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Theme
    |--------------------------------------------------------------------------
    |
    | Theme::Auto  → follow the visitor's `prefers-color-scheme`
    | Theme::Light → always light
    | Theme::Dark  → always dark
    |
    */

    'theme' => Theme::Auto,

    /*
    |--------------------------------------------------------------------------
    | Links shown to the visitor
    |--------------------------------------------------------------------------
    |
    | `home` accepts a URL, a route name, or null to fall back to the app root.
    | `support_email` is rendered on the status codes listed in
    | `support_email_codes` — no point offering support for a 404 typo.
    |
    */

    'links' => [
        'home' => env('ERROR_PAGES_HOME_URL', null),
        'home_route' => env('ERROR_PAGES_HOME_ROUTE', null),
        'login_route' => 'login',
        'support_email' => env('ERROR_PAGES_SUPPORT_EMAIL', null),
        'support_email_codes' => [401, 402, 403, 423, 500, 501, 502, 503, 504],
        'support_subject' => null, // null → "[:brand] :status — :message_number"
        'status_page' => env('ERROR_PAGES_STATUS_URL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Call-to-action buttons
    |--------------------------------------------------------------------------
    |
    | Built-in actions: 'home', 'back', 'reload', 'retry', 'login', 'support',
    | 'status_page'. An action that cannot resolve (no support e-mail, no login
    | route, no retry moment) removes itself silently.
    |
    | You may also inline a custom button:
    |
    |   404 => ['back', 'home', [
    |       'label' => 'Browse the catalogue',
    |       'url' => '/products',
    |       'icon' => 'magnifying-glass',
    |       'style' => 'ghost',
    |   ]],
    |
    | Available icons: see Vvdboogaard\ErrorPages\Support\Icons::names().
    | Styles: 'primary', 'secondary', 'ghost'.
    |
    */

    'actions' => [
        401 => ['login', 'home'],
        403 => ['home', 'support'],
        404 => ['back', 'home'],
        408 => ['reload', 'home'],
        410 => ['home'],
        419 => ['reload', 'home'],
        429 => ['retry', 'home'],
        500 => ['reload', 'home', 'support'],
        502 => ['reload', 'status_page', 'support'],
        503 => ['retry', 'status_page', 'support'],
        504 => ['reload', 'status_page'],
        'default' => ['back', 'home'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Messages
    |--------------------------------------------------------------------------
    |
    | A message passed to `abort(403, 'You may not edit a published post.')` is
    | far more useful than a generic line, so it replaces the translated message
    | on screen — but only for the status codes listed here.
    |
    | 404 and every 5xx are deliberately absent: a ModelNotFoundException leaks
    | your model class names and a 5xx message leaks internals. Add codes only
    | where *you* control the message that reaches abort().
    |
    */

    'messages' => [
        'use_exception_message' => true,
        'use_exception_message_codes' => [400, 402, 403, 409, 410, 423, 429],

        // Anything longer is a stack-trace-flavoured internal message, not a
        // sentence written for a human; the translated line is used instead.
        'max_exception_message_length' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Message number
    |--------------------------------------------------------------------------
    |
    | A short, deterministic code the visitor can quote in a support ticket.
    | It is a hash of *where* the error happened, so the same failure always
    | produces the same number — across servers, deploys and users. Two tickets
    | quoting ERR-3F9A2C are the same bug.
    |
    | origin:
    |   OriginStrategy::Application → first stack frame inside your app
    |                                 (recommended: a QueryException thrown deep
    |                                 inside Illuminate still points at your code)
    |   OriginStrategy::Thrown      → exact construction site of the exception
    |   OriginStrategy::RootCause   → deepest previous exception in the chain
    |
    | include_exception_class:
    |   Off by default so the same line always yields the same number. Turn on
    |   if you want a TypeError and a ValueError on one line to differ.
    |
    | salt:
    |   Prevents outsiders from confirming a guessed file path by comparing
    |   hashes. Keep it stable — changing it changes every message number.
    |
    */

    'message_number' => [
        'enabled' => true,
        'prefix' => env('ERROR_PAGES_PREFIX', 'ERR'),
        'separator' => '-',
        'length' => 6,
        'alphabet' => MessageNumberAlphabet::Hex,
        'algorithm' => 'crc32b',
        'origin' => OriginStrategy::Application,
        'include_exception_class' => false,
        'include_status_code' => false,
        'salt' => env('ERROR_PAGES_SALT', null),

        // Add the message number to the response as `X-Message-Number`.
        'response_header' => 'X-Message-Number',

        // Push the message number into Laravel's log context so the number on
        // screen can be grepped straight out of your logs (Laravel 11+).
        'log_context' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Request ID
    |--------------------------------------------------------------------------
    |
    | Headers are checked in order. AWS ALB, Google Cloud and W3C traceparent
    | formats are unwrapped to the bare trace id automatically.
    |
    */

    'request_id' => [
        'enabled' => true,
        'headers' => [
            'X-Request-Id',
            'X-Request-ID',
            'X-Correlation-Id',
            'X-Amzn-Trace-Id',
            'X-Cloud-Trace-Context',
            'CF-Ray',
            'traceparent',
        ],

        // Generate one when nothing upstream supplied it.
        'generate' => true,
        'generator' => 'uuid', // uuid | ulid | random

        // Echo the id back on every response. Set to null to disable.
        'response_header' => 'X-Request-Id',

        // Register the middleware that assigns an id at the start of the request
        // (so it is identical in your logs and on the error page).
        'middleware' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry-After
    |--------------------------------------------------------------------------
    |
    | Read from the exception's `Retry-After` / `X-RateLimit-Reset` headers and
    | from Laravel's maintenance mode. Shown as an absolute time plus a live
    | countdown, and the retry button stays disabled until the moment passes.
    |
    */

    'retry_after' => [
        'enabled' => true,
        'headers' => ['Retry-After', 'X-RateLimit-Reset'],

        // Longer than this and no countdown is shown: "try again in 3 days" is
        // not something a visitor can act on.
        'max_seconds' => 86400,

        // Absolute time format. Uses the app timezone.
        'time_format' => 'H:i',
        'datetime_format' => 'j M Y, H:i',

        // Live countdown, and auto-reload the moment it hits zero.
        'countdown' => true,
        'auto_reload' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exception details (the 500 block)
    |--------------------------------------------------------------------------
    |
    | DetailVisibility::Auto   → shown when app.debug is on, or when the current
    |                            environment is in `environments`
    | DetailVisibility::Always → always shown (be careful)
    | DetailVisibility::Never  → never shown, whatever the environment
    |
    | `Auto` is what you want: on staging (debug off, env staging) testers get a
    | copyable stack trace, while production shows nothing.
    |
    | `replace_debug_page` decides who wins locally. Off by default so you keep
    | Ignition/Whoops while developing; turn it on to preview these pages.
    |
    */

    'details' => [
        'visibility' => DetailVisibility::Auto,
        'environments' => ['local', 'development', 'testing', 'staging'],
        'replace_debug_page' => env('ERROR_PAGES_REPLACE_DEBUG_PAGE', false),

        // Only render the block for these status codes.
        'codes' => [500, 501, 502, 503, 504],

        'stack_frames' => 12,
        'collapsed' => true,

        // A copy button that puts a formatted report on the clipboard.
        'copyable' => true,

        // Extra context in the copied report. Never includes request bodies,
        // headers, session data or environment variables.
        'copy_includes' => [
            'url' => true,
            'method' => true,
            'timestamp' => true,
            'user_agent' => false,
            'app_version' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Livewire
    |--------------------------------------------------------------------------
    |
    | How an error during a Livewire round-trip is presented.
    |
    | LivewireErrorMode::Modal    → a positioned pop-up, page state preserved
    | LivewireErrorMode::Page     → replace the document with the full page
    | LivewireErrorMode::Disabled → leave Livewire's own handling alone
    |
    */

    'livewire' => [
        'mode' => LivewireErrorMode::Modal,
        'position' => ModalPosition::BottomRight,

        // Auto-inject the (≈3 KB, inline) handler into HTML responses. Turn off
        // and place `@errorPagesScripts` in your layout for full control.
        'inject_assets' => true,

        // Dismiss the pop-up automatically after N ms. 0 = stay until dismissed.
        'auto_dismiss' => 0,

        // Also intercept plain `fetch`/XHR failures raised by Alpine or your own
        // scripts, so an AJAX 500 surfaces instead of failing silently.
        'intercept_fetch' => false,

        'dismissible' => true,
        'max_width' => '26rem',
        'z_index' => 999999,
    ],

    /*
    |--------------------------------------------------------------------------
    | JSON / API responses
    |--------------------------------------------------------------------------
    |
    | For requests that expect JSON we return the same information in a machine
    | readable shape rather than an HTML page.
    |
    */

    'json' => [
        'enabled' => true,
        'include_message_number' => true,
        'include_request_id' => true,
        'include_retry_after' => true,
        'include_details' => true, // still obeys `details.visibility`
    ],

    /*
    |--------------------------------------------------------------------------
    | Filament (optional)
    |--------------------------------------------------------------------------
    |
    | When Filament is installed, inherit the active panel's branding so an
    | error inside /admin still looks like the panel. The package never requires
    | Filament; every option below is a no-op without it.
    |
    */

    'filament' => [
        'enabled' => true,

        // Only inherit when the failing request was aimed at a panel. Set to
        // false to use panel branding across the whole application.
        'only_on_panel_routes' => true,

        'inherit' => [
            'brand_name' => true,
            'brand_logo' => true,
            'primary_color' => true,
            'home_url' => true,
            'login_url' => true,
        ],

        // Which shade of the panel's primary colour to use (50…950).
        'color_shade' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Preview
    |--------------------------------------------------------------------------
    |
    | Registers /_error-pages and /_error-pages/{code} so you can design against
    | every state without triggering real errors. Add ?theme=dark to preview the
    | dark surface. Local environment only unless you change this.
    |
    */

    'preview' => [
        'enabled' => env('ERROR_PAGES_PREVIEW', null), // null → only in `local`
        'path' => '_error-pages',
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Views
    |--------------------------------------------------------------------------
    |
    | Publish with `php artisan vendor:publish --tag=error-pages-views` and the
    | published copies win. Per-code overrides are picked up automatically:
    | a view named `errors.404` in your app replaces this package's 404 page.
    |
    */

    'views' => [
        'page' => 'error-pages::error',
        'layout' => null, // e.g. 'layouts.app' to embed the error in your own chrome
        'prefer_application_views' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Miscellaneous
    |--------------------------------------------------------------------------
    */

    // Add `<meta name="robots" content="noindex, nofollow">`.
    'noindex' => true,

    // Show the timestamp of the error on the page.
    'show_timestamp' => true,

    // Fall back to the app locale; set explicitly to always render one language.
    'locale' => null,
];
