<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Error copy
|--------------------------------------------------------------------------
|
| Per status code:
|   title        — the headline
|   message      — one sentence: what happened, in the visitor's terms
|   reason       — why it happened
|   explanation  — extra context, only where it genuinely helps
|   suggestions  — concrete things the visitor can do next
|
| Lookup falls back from `404` to `4xx` to `default`, so you only need to
| define what differs. Placeholders: :status, :brand, :message_number,
| :support_email.
|
*/

return [

    'default' => [
        'title' => 'Something went wrong',
        'message' => 'We could not complete this request.',
        'reason' => 'The server responded with status :status.',
        'suggestions' => [
            'Try again in a moment.',
            'If it keeps happening, let us know and mention the message number below.',
        ],
    ],

    '4xx' => [
        'title' => 'This request could not be completed',
        'message' => 'The page or action you tried is not available.',
        'reason' => 'The request could not be processed by the server (status :status).',
        'suggestions' => [
            'Check the address for typos.',
            'Go back and try again from the previous page.',
        ],
    ],

    '5xx' => [
        'title' => 'Something went wrong on our side',
        'message' => 'This is not your fault — something failed while we were handling your request.',
        'reason' => 'The server ran into an unexpected problem (status :status).',
        'explanation' => 'The error has been logged. Quote the message number below and we can look up exactly what happened.',
        'suggestions' => [
            'Try again in a few minutes.',
            'If it keeps happening, contact us with the message number below.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 4xx — the request could not be honoured
    |--------------------------------------------------------------------------
    */

    '400' => [
        'title' => 'Bad request',
        'message' => 'We could not read this request.',
        'reason' => 'Something in the request was malformed or incomplete, so the server could not process it.',
        'explanation' => 'This usually happens after an interrupted upload, a link that got cut in half, or a browser extension modifying the page.',
        'suggestions' => [
            'Reload the page and try again.',
            'If you followed a link, check that the full address was copied.',
            'Disable browser extensions for this site and retry.',
        ],
    ],

    '401' => [
        'title' => 'You need to sign in',
        'message' => 'This page is only available when you are signed in.',
        'reason' => 'We could not verify who you are — you are either signed out or your session ended.',
        'explanation' => 'Sessions expire after a period of inactivity, which is why this can happen even though you signed in earlier.',
        'suggestions' => [
            'Sign in and open this page again.',
            'If you were already signed in, sign out and back in to refresh your session.',
        ],
    ],

    '402' => [
        'title' => 'Payment required',
        'message' => 'This feature is not available on your current plan.',
        'reason' => 'Access to this part of the application requires an active subscription or a settled invoice.',
        'suggestions' => [
            'Check your subscription and billing details.',
            'Contact us at :support_email if you believe your account should have access.',
        ],
    ],

    '403' => [
        'title' => 'You do not have access',
        'message' => 'Your account is not allowed to open this page.',
        'reason' => 'You are signed in, but this page requires permissions your account does not have.',
        'explanation' => 'Access is granted per role. If you need this page for your work, an administrator can grant it.',
        'suggestions' => [
            'Check whether you are signed in with the right account.',
            'Ask an administrator to grant you access.',
            'Go back to a page you do have access to.',
        ],
    ],

    '404' => [
        'title' => 'We could not find this page',
        'message' => 'The page you are looking for does not exist, or is no longer available.',
        'reason' => 'The address is unknown to us. It may have been moved, renamed or removed.',
        'explanation' => 'Bookmarks and old links break when pages move — this is almost always the cause.',
        'suggestions' => [
            'Check the address for typos.',
            'Go back to the previous page.',
            'Start over from the home page.',
        ],
    ],

    '405' => [
        'title' => 'This action is not allowed here',
        'message' => 'This page does not accept the action you tried.',
        'reason' => 'The address exists, but not for this type of request.',
        'explanation' => 'This usually means a form was submitted to the wrong place, or a page was refreshed after a submission.',
        'suggestions' => [
            'Go back and open the page again instead of refreshing.',
            'Start the action over from the beginning.',
        ],
    ],

    '408' => [
        'title' => 'The request took too long',
        'message' => 'We did not receive your request in time.',
        'reason' => 'The connection was too slow or was interrupted before the request finished.',
        'suggestions' => [
            'Check your internet connection.',
            'Try again — this is often temporary.',
        ],
    ],

    '409' => [
        'title' => 'This change conflicts with another one',
        'message' => 'Someone else changed this at the same time as you.',
        'reason' => 'The data changed between the moment you opened this page and the moment you saved, so applying your change would overwrite theirs.',
        'suggestions' => [
            'Reload the page to see the current version.',
            'Apply your change again on top of the up-to-date data.',
        ],
    ],

    '410' => [
        'title' => 'This page is gone',
        'message' => 'This page existed, but has been permanently removed.',
        'reason' => 'Unlike a broken link, this address was deliberately retired — there is no new location for it.',
        'suggestions' => [
            'Remove this page from your bookmarks.',
            'Start over from the home page.',
        ],
    ],

    '413' => [
        'title' => 'That file is too large',
        'message' => 'The file you tried to upload exceeds the maximum size.',
        'reason' => 'The server rejects uploads above a fixed limit to keep the application responsive for everyone.',
        'suggestions' => [
            'Compress the file or split it into smaller parts.',
            'For images, try a smaller resolution or a JPEG instead of a PNG.',
        ],
    ],

    '419' => [
        'title' => 'Your session expired',
        'message' => 'This page was open too long, so we could not verify your submission.',
        'reason' => 'For your security, forms are only valid for a limited time. That window closed before you submitted.',
        'explanation' => 'This protects you against another site submitting forms on your behalf. Your data was not lost — reloading brings back the form.',
        'suggestions' => [
            'Reload the page and fill in the form again.',
            'Copy anything you typed before reloading, so you do not lose it.',
        ],
    ],

    '423' => [
        'title' => 'This item is locked',
        'message' => 'You cannot change this right now.',
        'reason' => 'The item is locked, either by another user who is editing it or by a process that is still running.',
        'suggestions' => [
            'Wait a moment and try again.',
            'Contact us at :support_email if it stays locked.',
        ],
    ],

    '429' => [
        'title' => 'Too many requests',
        'message' => 'You have made too many requests in a short time.',
        'reason' => 'A rate limit protects the application against overload. You have hit that limit and it will reset shortly.',
        'explanation' => 'The limit resets automatically — you do not need to do anything except wait.',
        'suggestions' => [
            'Wait for the moment shown above, then try again.',
            'Avoid refreshing repeatedly; that extends the wait.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 5xx — the server failed
    |--------------------------------------------------------------------------
    */

    '500' => [
        'title' => 'Something went wrong on our side',
        'message' => 'This is not your fault — something failed while we were handling your request.',
        'reason' => 'The application ran into an unexpected error and could not finish the request.',
        'explanation' => 'The error has been logged automatically. Quote the message number below and we can look up exactly what happened.',
        'suggestions' => [
            'Try again in a few minutes.',
            'If it keeps happening, send us the message number below.',
            'Nothing you entered was lost unless the page says otherwise.',
        ],
    ],

    '501' => [
        'title' => 'This is not available yet',
        'message' => 'The application does not support this action.',
        'reason' => 'This feature has not been implemented on the server.',
        'suggestions' => [
            'Contact us at :support_email if you were expecting this to work.',
        ],
    ],

    '502' => [
        'title' => 'We could not reach the server',
        'message' => 'An underlying service did not respond correctly.',
        'reason' => 'A service this application depends on returned an invalid response.',
        'explanation' => 'This is usually short-lived and resolves without any action on your part.',
        'suggestions' => [
            'Try again in a minute.',
            'Check the status page for known incidents.',
        ],
    ],

    '503' => [
        'title' => 'We are temporarily unavailable',
        'message' => 'The application is down for maintenance or is under heavy load right now.',
        'reason' => 'The server is deliberately not accepting requests at this moment.',
        'explanation' => 'Maintenance windows are short. If a time is shown above, that is when we expect to be back.',
        'suggestions' => [
            'Try again at the time shown above.',
            'Check the status page for the latest information.',
        ],
    ],

    '504' => [
        'title' => 'The server took too long',
        'message' => 'Your request did not complete within the allowed time.',
        'reason' => 'A service this application depends on responded too slowly, so the request was cancelled.',
        'explanation' => 'Large reports and exports are the usual cause. A smaller selection often succeeds.',
        'suggestions' => [
            'Try again with a smaller date range or selection.',
            'Try again in a few minutes.',
        ],
    ],
];
