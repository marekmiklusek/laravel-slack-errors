<p align="center">
  <img src="art/banner.svg" alt="Laravel Slack Errors" width="100%">
</p>

<p align="center">
  <a href="https://github.com/marekmiklusek/laravel-slack-errors/actions"><img src="https://github.com/marekmiklusek/laravel-slack-errors/actions/workflows/tests.yml/badge.svg" alt="Tests"></a>
  <a href="https://packagist.org/packages/marekmiklusek/laravel-slack-errors"><img src="https://img.shields.io/packagist/v/marekmiklusek/laravel-slack-errors.svg" alt="Latest Stable Version"></a>
  <a href="https://packagist.org/packages/marekmiklusek/laravel-slack-errors"><img src="https://img.shields.io/packagist/dt/marekmiklusek/laravel-slack-errors.svg" alt="Downloads"></a>
  <a href="https://github.com/marekmiklusek/laravel-slack-errors/blob/main/LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="License"></a>
</p>

Install the package, add a webhook URL to `.env` and you are done. No changes to
`bootstrap/app.php`, no custom exception handler.

## Features

- ⚡ **Zero config**: hooks into the framework exception handler automatically
- 🧱 **Block Kit messages**: exception, location, URL, command or queue job, user and stack trace
- 🔁 **Throttling**: the same exception is sent once per 10 minutes, so a loop never floods the channel
- 🌍 **Environment filter** with wildcard support, `production` only by default
- 🙈 **Ignore list** on top of your application's own `dontReport`
- 🛡️ **Fail-safe**: a failing Slack call never breaks or re-enters your error handling
- 🔍 **No side effects**: the user is reported only if the app already loaded it, no extra queries
- 🧪 **Test command**: `php artisan slack-errors:test` checks the webhook in one step

## Requirements

- PHP 8.5+
- Laravel 13+

## Installation

```bash
composer require marekmiklusek/laravel-slack-errors
```

Create an [incoming webhook](https://api.slack.com/messaging/webhooks) for your
Slack app, pick the channel and add the URL to `.env`:

```dotenv
SLACK_ERRORS_WEBHOOK_URL=https://hooks.slack.com/services/...
```

Check the setup:

```bash
php artisan slack-errors:test
```

The command sends a test message regardless of the environment filter and prints
Slack's error if the webhook does not work.

That's it. Every exception your application reports in `production` now lands in
the channel.

## Configuration

Everything is configurable through `.env`:

| Variable | Default | Description |
| --- | --- | --- |
| `SLACK_ERRORS_WEBHOOK_URL` | | Incoming webhook URL. Nothing is sent while it is empty. |
| `SLACK_ERRORS_ENABLED` | `true` | Master switch. |
| `SLACK_ERRORS_ENVIRONMENTS` | `production` | Comma separated environments, wildcards allowed (`*` for all). |
| `SLACK_ERRORS_MENTION` | | Prepended to every message, e.g. `<!channel>`, `<!here>` or `<@U012AB3CD>`. |
| `SLACK_ERRORS_TIMEOUT` | `3` | Seconds to wait for Slack. |
| `SLACK_ERRORS_THROTTLE` | `10` | Minutes during which the same exception is sent only once. `0` disables it. |
| `SLACK_ERRORS_CACHE_STORE` | `file` | Cache store used by the throttle. The local `file` store keeps working when Redis or the database is down. |

To change the bar color or ignore specific exception classes (subclasses included), publish
the config:

```bash
php artisan vendor:publish --tag=slack-errors-config
```

```php
// config/slack-errors.php
'color' => '#ECB22E',

'ignore' => [
    Illuminate\Validation\ValidationException::class,
],
```

Exceptions listed in your application's `dontReport` are never sent either, and neither are
exceptions with their own `report()` method or ones stopped by a `reportable(...)->stop()`
callback registered before this package.

`QueryException` messages contain the SQL with its bindings (emails, tokens). To keep those values
out of Slack, enable binding masking on your database connection:

```php
// config/database.php, inside a connection
'mask_bindings_in_exception_messages' => true,
```

## What the message contains

| Field | Value |
| --- | --- |
| Header | App name and environment |
| Exception | Class and message |
| Location | File and line, relative to the project root |
| URL | HTTP method and route template for web requests (`/reset-password/{token}`), without the query string. Only the domain when the error happens before routing |
| Command, Job | The artisan command name without its arguments, or the job class inside queue workers |
| User | ID of the authenticated user for web requests, if the request already loaded one |
| Stack trace | Up to 10 frames from your own code, vendor and entry points skipped |

## How it works

- The service provider registers a `reportable` callback on the framework
  exception handler, so it runs for everything Laravel reports, including
  `report($e)` calls.
- The message is sent synchronously with a short timeout. Queues are not used on
  purpose: when something breaks, the queue or Redis is often part of the
  problem.
- Repeats of the same exception are held back for the throttle window. An exception
  is identified by its class and where it comes from in your code: for exceptions
  thrown inside vendor (every `QueryException`), that is the first frame of your own
  code. The message is left out on purpose, so IDs in it do not bypass the throttle.
  A cache that is unavailable or keeps nothing (the `null` driver) never holds a
  notification back.
- When Slack is unreachable, rate limits or fails, sending pauses for a minute, so
  requests and workers never wait for it during an outage.
- Any failure while talking to Slack is swallowed, and an exception thrown while
  a report is in progress is not reported again.

## Development

| Command | What it does |
| --- | --- |
| `composer test` | Run every quality gate the CI pipeline runs |
| `composer test:unit` | Run the test suite with exactly 100% code coverage |
| `composer test:type-coverage` | Require 100% type coverage |
| `composer test:types` | Run Larastan static analysis |
| `composer test:lint` | Check code style and pending refactorings without writing files |
| `composer lint` | Apply Rector refactorings and fix code style |

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
