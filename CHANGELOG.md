# Changelog

All notable changes to `laravel-herdr` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- `Herdr::core()`: a client for herdr-core, the backend of the Herdr desktop app. It lists, finds, reads, types into, presses keys in, kills and starts terminals, and reads documents and the server status.
- `core_socket_path` (`HC_SOCKET`) and `core_session` (`HC_SESSION`) settings. The core socket resolves like `hc` does.

### Changed

- `RequestException::code()` returns the herdr-core error kind (for example `not_found`) for JSON-RPC errors.

## [0.2.0] - 2026-08-01

### Added

- Laravel 13 support.

## [0.1.0] - 2026-07-31

### Added

- Initial release: fluent client for the Herdr socket API (Herdr 0.7.5, socket protocol 17).
- Resources for workspaces, tabs, panes, agents, worktrees, layouts, plugins and integrations.
- Event subscriptions, `waitFor()` and client-side event streams.
- `Herdr::fake()` testing support with response stubs and sent-request assertions.

[0.2.0]: https://github.com/martin-ro/laravel-herdr/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/martin-ro/laravel-herdr/releases/tag/v0.1.0
