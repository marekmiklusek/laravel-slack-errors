<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Master switch for sending exceptions to Slack.
    |
    */

    'enabled' => (bool) env('SLACK_ERRORS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Webhook URL
    |--------------------------------------------------------------------------
    |
    | The incoming webhook URL of the Slack channel. Nothing is sent while
    | it is empty.
    |
    */

    'webhook_url' => env('SLACK_ERRORS_WEBHOOK_URL'),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Comma separated list of environments that send notifications.
    | Wildcards are supported, so "*" sends from every environment.
    |
    */

    'environments' => array_values(array_filter(array_map(
        mb_trim(...),
        explode(',', (string) env('SLACK_ERRORS_ENVIRONMENTS', 'production')),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Mention
    |--------------------------------------------------------------------------
    |
    | Optional mention prepended to every message, e.g. "<!channel>",
    | "<!here>" or a user mention like "<@U012AB3CD>".
    |
    */

    'mention' => env('SLACK_ERRORS_MENTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for Slack. Keep it short, the request is sent while
    | the application handles the exception.
    |
    */

    'timeout' => (int) env('SLACK_ERRORS_TIMEOUT', 3),

    /*
    |--------------------------------------------------------------------------
    | Ignored Exceptions
    |--------------------------------------------------------------------------
    |
    | Exception classes (and their subclasses) never sent to Slack. The
    | application's own "dontReport" list is respected as well.
    |
    */

    'ignore' => [
        // Illuminate\Validation\ValidationException::class,
    ],

];
