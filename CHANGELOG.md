# Changelog

All notable changes to this package are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Send every reported exception to a Slack incoming webhook, formatted with Block Kit.
- Message context: app, environment, host, file and line, URL or console command, IP,
  authenticated user and the application frames of the stack trace.
- Environment filter with wildcard support, ignored exception classes and a master switch.
- Recursion guard: a failing Slack call never breaks or re-enters error reporting.
- Throttling: the same exception (class, file and line) is sent once per window, 10 minutes
  by default, with a configurable cache store. An unavailable cache never blocks a notification.
- `slack-errors:test` command to verify the webhook.
- Queue job class in the message context when the exception happens inside a worker.
