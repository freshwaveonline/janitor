<?php

declare(strict_types=1);

return [

    'actions' => [
        'home' => 'Go to home page',
        'back' => 'Go back',
        'reload' => 'Try again',
        'retry' => 'Try again',
        'login' => 'Sign in',
        'support' => 'Contact support',
        'status_page' => 'Status page',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'dismiss' => 'Dismiss',
        'show_details' => 'Technical details',
    ],

    'headings' => [
        'reason' => 'What happened',
        'suggestions' => 'What you can do',
        'support' => 'Still stuck?',
    ],

    'meta' => [
        'message_number' => 'Message number',
        'request_id' => 'Request ID',
        'timestamp' => 'Time',
        'status' => 'Status',
        'copy_hint' => 'Click to copy',
    ],

    'retry' => [
        'heading' => 'When to try again',
        'at' => 'You can try again at :time.',
        'at_datetime' => 'You can try again on :datetime.',
        'in' => 'Try again in :duration.',
        'now' => 'You can try again now.',
        'seconds' => ':count second|:count seconds',
        'minutes' => ':count minute|:count minutes',
        'hours' => ':count hour|:count hours',
    ],

    'details' => [
        'heading' => 'Technical details',
        'intro' => 'Shown because this environment allows it. Copy this into your ticket.',
        'copy' => 'Copy report',
        'stack_trace' => 'Stack trace',
        'caused_by' => 'Caused by',
        'vendor_frame' => 'vendor',
    ],

    'support' => [
        'email' => 'Email us at :email',
        'with_number' => 'Mention message number :number so we can find this error straight away.',
    ],

    'aria' => [
        'error_icon' => 'Error',
        'close' => 'Close',
    ],
];
