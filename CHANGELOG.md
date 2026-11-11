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
- Compact message: app name and environment, exception class, message and location, plus the URL
  (without the query string), the artisan command or queue job class, and the authenticated user
  when the app already loaded one.
- Stack trace limited to frames from the project's own code, skipping vendor and entry points.
- Throttling: the same exception (class, file and line) is sent once per window, 10 minutes by
  default, with a configurable cache store. An unavailable cache never blocks a notification.
- Environment filter with wildcard support, ignored exception classes and a master switch.
- Optional mention prepended to every message and a configurable bar color.
- Recursion guard: a failing Slack call never breaks or re-enters error reporting.
- `slack-errors:test` command to verify the webhook.

[Unreleased]: https://github.com/marekmiklusek/laravel-slack-errors/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/marekmiklusek/laravel-slack-errors/releases/tag/v1.0.0
