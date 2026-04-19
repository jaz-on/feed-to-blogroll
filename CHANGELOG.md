# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

## [1.1.0] - 2026-04-18

### Added

- Plugin headers `GitHub Plugin URI` and `Primary Branch` for [Git Updater](https://github.com/git-updater/git-updater) (track updates from GitHub; `dev` uses the `dev` branch, `main` uses `main` after release).
- [GitHub Actions](https://github.com/jaz-on/feed-to-blogroll/actions) workflow running `composer phpcs` on pushes and pull requests to `main` and `dev`.
- [`.gitattributes`](.gitattributes) `export-ignore` rules so `git archive` ZIPs omit Composer, PHPCS, and other development-only paths (aligned with [`.distignore`](.distignore)).

### Changed

- README: branch strategy (`main` stable vs `dev` integration), Git Updater installation, manual verification of updates, and fixed REST API / block documentation formatting.
- Aligned plugin version to **1.1.0** across the main plugin file, `FEED_TO_BLOGROLL_VERSION`, `block.json`, `plugin.json`, and the POT header.
- PHPCS: increased `absoluteLineLimit` for long admin HTML/translatable strings; PHPCBF fixes in CPT, template, and main plugin class.

## [1.0.0] - Initial release

- Feedbin API synchronization, `blogroll` custom post type with native meta fields (no ACF dependency).
- Admin UI, shortcodes `[blogroll]` / `[blogroll_grid]`, block `feed-to-blogroll/blogroll`, REST API, OPML export, categories/tags, cron sync, security hardening, caching, and accessibility-oriented markup.
