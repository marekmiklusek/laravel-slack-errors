# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-11

### Added

- Send every reported exception to a Slack incoming webhook, formatted with Block Kit in a colored
  attachment, so consecutive messages stay visually separate.
- Hook into the framework exception handler without any setup, including the console and queue
  workers where Collision decorates the handler.
- Compact message: app name and environment, exception class, message and location, plus the route
  template of the URL (without the query string), the artisan command name (without arguments) or
  queue job class, and the authenticated user when the app already loaded one.
- Invalid UTF-8 in exception messages is replaced, so the payload can always be encoded.
- Stack trace limited to frames from the project's own code, skipping vendor and entry points.
- Throttling: the same exception (class and origin in the project's code) is sent once per window,
  10 minutes by default. It uses the local `file` cache store by default, so it keeps working when
  Redis or the database is down. A cache that is unavailable or keeps nothing never holds a
  notification back, and a payload Slack rejects does not use up the window.
- Sending pauses for a minute while Slack is unreachable, rate limits or fails, so requests and
  workers never wait for it during an outage.
- Environment filter with wildcard support, ignored exception classes and a master switch.
- Optional mention prepended to every message and a configurable bar color.
- Recursion guard: a failing Slack call never breaks or re-enters error reporting.
- `slack-errors:test` command to verify the webhook, without ever printing the webhook URL.

[Unreleased]: https://github.com/marekmiklusek/laravel-slack-errors/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/marekmiklusek/laravel-slack-errors/releases/tag/v1.0.0
